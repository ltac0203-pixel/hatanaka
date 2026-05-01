export function toTrimmedString(value: unknown): string | null {
    if (typeof value !== "string") {
        return null;
    }

    const normalized = value.trim();
    return normalized === "" ? null : normalized;
}

export function findStringValue(
    input: unknown,
    keys: string[],
    seen = new Set<object>(),
): string | null {
    if (!input || typeof input !== "object") {
        return null;
    }

    if (seen.has(input)) {
        return null;
    }
    seen.add(input);

    const record = input as Record<string, unknown>;
    for (const key of keys) {
        const value = toTrimmedString(record[key]);
        if (value) {
            return value;
        }
    }

    for (const value of Object.values(record)) {
        const found = findStringValue(value, keys, seen);
        if (found) {
            return found;
        }
    }

    return null;
}
