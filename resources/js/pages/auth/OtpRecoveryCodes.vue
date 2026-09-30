<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

defineProps<{
    recoveryCodes: string[];
}>();

defineOptions({
    layout: {
        title: 'Códigos de Recuperación',
        description: 'Guarda estos códigos en un lugar seguro',
    },
});
</script>

<template>
    <Head title="Códigos de Recuperación" />

    <div class="flex flex-col gap-6">
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 dark:bg-amber-950 dark:border-amber-800">
            <p class="text-sm text-amber-800 dark:text-amber-200 font-medium">
                Importante: Guarda estos códigos en un lugar seguro.
            </p>
            <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">
                Cada código solo se puede usar una vez. Si pierdes acceso a tu app de autenticación, necesitarás estos códigos para acceder a tu cuenta.
            </p>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Tus códigos de recuperación</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-2 gap-2">
                    <div v-for="(code, index) in recoveryCodes" :key="index"
                         class="bg-muted p-2 rounded text-center font-mono text-sm">
                        {{ code }}
                    </div>
                </div>
            </CardContent>
        </Card>

        <Button as-child class="w-full">
            <Link href="/dashboard">Ir al Dashboard</Link>
        </Button>
    </div>
</template>
