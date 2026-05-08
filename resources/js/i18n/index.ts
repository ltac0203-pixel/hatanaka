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
            // dev では即座に気付けるよう例外を投げ、prod ではキー文字列にフォールバックして
            // ja.ts の構造変更が起きても画面全体が真っ白にならないようにする。
            const message = `Translation key resolution failed: ${key}`;
            if (import.meta.env.DEV) {
                throw new Error(message);
            }
            console.error(message);
            return key as PathValue<typeof ja, K>;
        }

        result = result[part];
    }

    return result as PathValue<typeof ja, K>;
}

export { ja };
