export function isValidPublicKey(key: string): boolean {
    return /^p_(test|live)_[A-Za-z0-9]+$/.test(key);
}
