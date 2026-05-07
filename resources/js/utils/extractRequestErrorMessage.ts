export type RequestErrors = Record<string, string | string[] | undefined>;

export function extractRequestErrorMessage(
    errors: RequestErrors,
    fallbackMessage: string,
) {
    for (const value of Object.values(errors)) {
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
