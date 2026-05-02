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
        // Fincode UI Components の getFormData() が返すキー
        "month",
    ]);
    let expireYear = findStringValue(formData, [
        "expire_year",
        "expireYear",
        "exp_year",
        "expYear",
        // Fincode UI Components の getFormData() が返すキー
        "year",
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

    // Fincode tokens API は expire を YYMM (年下2桁 + 月) 形式の 4 桁文字列で要求する。
    const payload: FincodeTokenCardPayload = {
        card_no: cardNo,
        expire: `${expireYear}${expireMonth}`,
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
        // Fincode UI Components の getFormData() が返すキー (大文字)
        "CVC",
    ])?.replace(/\D/g, "");
    if (securityCode) {
        payload.security_code = securityCode;
    }

    return payload;
}
