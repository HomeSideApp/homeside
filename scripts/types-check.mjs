#!/usr/bin/env node
/**
 * Typecheck gate that treats Wayfinder's generated TypeScript as opaque.
 *
 * `vue-tsc --noEmit` cannot go green while the generated modules live in the same program.
 * Wayfinder emits declarations that no compiler accepts — for example the URI dictionary it
 * writes when two routes (`PUT` and `PATCH`) resolve to the same controller action, which
 * repeats the same object key (TS1117). Those trees are gitignored build artifacts, regenerated
 * on every build, so they are neither hand-editable nor safe to patch after generation: in the
 * Sail container they are written as `root`, which leaves a host-side rewrite with EACCES.
 *
 * This wrapper keeps the generated tree resolvable (hand-written code still gets its real
 * types, including route argument checking at the call site) while ignoring only the
 * diagnostics whose file lives inside it. Everything else is reported verbatim and fails the
 * command, so the check stays blocking for hand-written code.
 *
 * It fails closed: if vue-tsc exits non-zero without emitting a single parseable diagnostic
 * (a compiler crash or an output format change), that exit code is propagated.
 */
import { spawn } from 'node:child_process';
import path from 'node:path';

const VUE_TSC_ENTRY = path.join('node_modules', 'vue-tsc', 'bin', 'vue-tsc.js');
const GENERATED_TREES = [
    'resources/js/actions/',
    'resources/js/routes/',
    'resources/js/wayfinder/',
];
const DIAGNOSTIC = /^(.+?)\(\d+,\d+\): (?:error|warning) TS\d+: /;

function runVueTsc(extraArguments) {
    return new Promise((resolve, reject) => {
        const child = spawn(
            process.execPath,
            [VUE_TSC_ENTRY, '--noEmit', '--pretty', 'false', ...extraArguments],
            { cwd: process.cwd(), stdio: ['ignore', 'pipe', 'pipe'] },
        );

        let stdout = '';
        let stderr = '';

        child.stdout.on('data', (chunk) => {
            stdout += chunk;
        });

        child.stderr.on('data', (chunk) => {
            stderr += chunk;
        });

        child.on('error', reject);
        child.on('close', (exitCode) => {
            resolve({ exitCode: exitCode ?? 1, output: stdout + stderr });
        });
    });
}

function isGenerated(location) {
    const normalized = location.split(path.sep).join('/').replace(/^\.\//, '');

    return GENERATED_TREES.some(
        (tree) =>
            normalized.startsWith(tree) || normalized.includes(`/${tree}`),
    );
}

/**
 * Groups vue-tsc output into diagnostic blocks (a `file(line,col): error ...` line plus its
 * indented continuation lines) and untouched passthrough lines, keeping the original order.
 */
function groupOutput(output) {
    const blocks = [];
    let current = null;

    for (const line of output.split('\n')) {
        const diagnostic = DIAGNOSTIC.exec(line);

        if (diagnostic) {
            current = { location: diagnostic[1], lines: [line] };
            blocks.push(current);

            continue;
        }

        if (current && (line.startsWith('  ') || line.startsWith('\t'))) {
            current.lines.push(line);

            continue;
        }

        current = null;
        blocks.push({ location: null, lines: [line] });
    }

    return blocks;
}

async function main() {
    const { exitCode, output } = await runVueTsc(process.argv.slice(2));
    const blocks = groupOutput(output);

    let ignoredDiagnostics = 0;
    let reportedDiagnostics = 0;
    const printable = [];

    for (const block of blocks) {
        if (block.location !== null && isGenerated(block.location)) {
            ignoredDiagnostics += 1;

            continue;
        }

        if (block.location !== null) {
            reportedDiagnostics += 1;
        }

        printable.push(...block.lines);
    }

    const remaining = printable.join('\n').replace(/\n+$/, '');

    if (remaining.length > 0) {
        console.log(remaining);
    }

    if (ignoredDiagnostics > 0) {
        console.log(
            `types:check ignored ${ignoredDiagnostics} diagnostic(s) reported inside the generated Wayfinder trees.`,
        );
    }

    if (reportedDiagnostics > 0) {
        console.error(
            `types:check failed with ${reportedDiagnostics} diagnostic(s) in hand-written code.`,
        );
        process.exit(1);
    }

    if (exitCode !== 0 && ignoredDiagnostics === 0) {
        // No parseable diagnostics at all: the compiler failed for a reason we cannot classify.
        process.exit(exitCode);
    }

    process.exit(0);
}

await main();
