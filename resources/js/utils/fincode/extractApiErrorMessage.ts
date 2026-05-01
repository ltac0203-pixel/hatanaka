import { toTrimmedString } from "./helpers";

export function extractApiErrorMessage(
    response: FincodeTokenResponse,
): string | null {
    if (!Array.isArray(response.errors) || response.errors.length === 0) {
        return null;
    }

    const message = toTrimmedString(response.errors[0]?.error_message);
    return message ?? null;
}
