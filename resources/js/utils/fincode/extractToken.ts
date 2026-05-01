export function extractToken(response: FincodeTokenResponse): string | null {
    if (!Array.isArray(response.list) || response.list.length === 0) {
        return null;
    }

    const token = response.list[0]?.token;
    if (typeof token !== "string" || token.trim() === "") {
        return null;
    }

    return token;
}
