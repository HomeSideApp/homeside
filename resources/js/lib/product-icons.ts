const staticIconNamePattern = /^[a-z0-9][a-z0-9_-]*\.webp$/i;

export function productIconUrl(icon: string | null | undefined): string | null {
    if (!icon) {
        return null;
    }

    if (/^https?:\/\//i.test(icon) || icon.startsWith('/images/')) {
        return icon;
    }

    if (!staticIconNamePattern.test(icon)) {
        return null;
    }

    return `/icons/webp/${icon}`;
}
