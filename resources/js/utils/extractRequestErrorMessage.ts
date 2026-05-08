export type RequestErrors = Record<string, string | string[] | undefined>;

export interface ExtractRequestErrorMessageOptions {
    skipKeys?: ReadonlySet<string>;
}

export function extractRequestErrorMessage(
    errors: RequestErrors,
    fallbackMessage: string,
    options?: ExtractRequestErrorMessageOptions,
): string;
export function extractRequestErrorMessage(
    errors: RequestErrors,
    fallbackMessage: null,
    options?: ExtractRequestErrorMessageOptions,
): string | null;
export function extractRequestErrorMessage(
    errors: RequestErrors,
    fallbackMessage: string | null,
    options: ExtractRequestErrorMessageOptions = {},
): string | null {
    const { skipKeys } = options;

    for (const [key, value] of Object.entries(errors)) {
        if (skipKeys?.has(key)) {
            continue;
        }

        if (typeof value === "string" && value.trim() !== "") {
            return value;
        }

        if (Array.isArray(value)) {
            const message = value.find(
                (item): item is string =>
                    typeof item === "string" && item.trim() !== "",
            );

            if (message) {
                return message;
            }
        }
    }

    return fallbackMessage;
}
