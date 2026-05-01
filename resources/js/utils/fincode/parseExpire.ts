export function parseExpire(
    value: string,
): { month: string; year: string } | null {
    const digits = value.replace(/\D/g, "");
    if (digits.length < 4) {
        return null;
    }

    const month = digits.slice(0, 2);
    const year = digits.length >= 6 ? digits.slice(-2) : digits.slice(2);
    if (month === "" || year === "") {
        return null;
    }

    return { month, year };
}
