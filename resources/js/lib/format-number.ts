export function formatCompactNumber(value: number): string {
    if (!Number.isFinite(value)) {
        return '0';
    }

    const abs = Math.abs(value);

    if (abs < 1_000) {
        return `${Math.round(value)}`;
    }

    if (abs < 1_000_000) {
        return `${formatWithSuffix(value / 1_000)}K`;
    }

    return `${formatWithSuffix(value / 1_000_000)}M`;
}

function formatWithSuffix(scaled: number): string {
    const abs = Math.abs(scaled);

    if (abs < 10) {
        return scaled.toFixed(1);
    }

    return `${Math.round(scaled)}`;
}
