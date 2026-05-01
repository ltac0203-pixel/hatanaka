import { ja } from "./ja";

// ネストオブジェクトの末端パスを "a.b.c" 形式で列挙する型
type Leaves<T, Prefix extends string = ""> = T extends string
    ? Prefix
    : {
          [K in keyof T & string]: Leaves<
              T[K],
              Prefix extends "" ? K : `${Prefix}.${K}`
          >;
      }[keyof T & string];

export type TranslationKey = Leaves<typeof ja>;

// パスから値の型を解決する型
type PathValue<T, P extends string> = P extends `${infer K}.${infer Rest}`
    ? K extends keyof T
        ? PathValue<T[K], Rest>
        : never
    : P extends keyof T
      ? T[P]
      : never;

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === "object" && value !== null;
}

export function t<K extends TranslationKey>(key: K): PathValue<typeof ja, K> {
    const parts = key.split(".");
    let result: unknown = ja;

    for (const part of parts) {
        if (!isRecord(result) || !(part in result)) {
            throw new Error(`Translation key resolution failed: ${key}`);
        }

        result = result[part];
    }

    return result as PathValue<typeof ja, K>;
}

export { ja };
