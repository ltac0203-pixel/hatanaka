export interface StableFeatureItem {
    key: string;
    label: string;
}

export function toStableFeatureItems(
    features: readonly string[],
): StableFeatureItem[] {
    const occurrences = new Map<string, number>();

    return features.map((feature) => {
        const normalizedLabel = feature.trim();
        const occurrence = occurrences.get(normalizedLabel) ?? 0;

        occurrences.set(normalizedLabel, occurrence + 1);

        return {
            key: `feature:${normalizedLabel}:${occurrence}`,
            label: feature,
        };
    });
}
