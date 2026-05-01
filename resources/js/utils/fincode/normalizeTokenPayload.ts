import { findStringValue } from "./helpers";
import { parseExpire } from "./parseExpire";

export function normalizeTokenPayload(
    formData: Record<string, unknown>,
): FincodeTokenCardPayload | null {
    const cardNo = findStringValue(formData, [
        "card_no",
        "cardNo",
        "cardNumber",
    ])?.replace(/\D/g, "");
    let expireMonth = findStringValue(formData, [
        "expire_month",
        "expireMonth",
        "exp_month",
        "expMonth",
    ]);
    let expireYear = findStringValue(formData, [
        "expire_year",
        "expireYear",
        "exp_year",
        "expYear",
    ]);

    if (!expireMonth || !expireYear) {
        const combinedExpire = findStringValue(formData, [
            "expire",
            "expiration",
        ]);
        if (combinedExpire) {
            const parsed = parseExpire(combinedExpire);
            if (parsed) {
                expireMonth ??= parsed.month;
                expireYear ??= parsed.year;
            }
        }
    }

    if (!cardNo || !expireMonth || !expireYear) {
        return null;
    }

    expireMonth = expireMonth.replace(/\D/g, "");
    expireYear = expireYear.replace(/\D/g, "");
    if (expireMonth.length === 1) {
        expireMonth = `0${expireMonth}`;
    }
    if (expireYear.length === 4) {
        expireYear = expireYear.slice(-2);
    }

    if (expireMonth === "" || expireYear === "") {
        return null;
    }

    const payload: FincodeTokenCardPayload = {
        card_no: cardNo,
        expire_month: expireMonth,
        expire_year: expireYear,
    };

    const holderName = findStringValue(formData, ["holder_name", "holderName"]);
    if (holderName) {
        payload.holder_name = holderName;
    }

    const securityCode = findStringValue(formData, [
        "security_code",
        "securityCode",
        "cvc",
        "cvv",
    ])?.replace(/\D/g, "");
    if (securityCode) {
        payload.security_code = securityCode;
    }

    return payload;
}
