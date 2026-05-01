<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attributeを承認してください。',
    'accepted_if' => ':otherが:valueの場合、:attributeを承認してください。',
    'active_url' => ':attributeは有効なURLではありません。',
    'after' => ':attributeは:date以降の日付を指定してください。',
    'after_or_equal' => ':attributeは:date以降の日付を指定してください。',
    'alpha' => ':attributeは英字のみ使用できます。',
    'alpha_dash' => ':attributeは英数字とダッシュ(-)、アンダースコア(_)のみ使用できます。',
    'alpha_num' => ':attributeは英数字のみ使用できます。',
    'array' => ':attributeは配列でなければなりません。',
    'ascii' => ':attributeは半角英数字と記号のみ使用できます。',
    'before' => ':attributeは:date以前の日付を指定してください。',
    'before_or_equal' => ':attributeは:date以前の日付を指定してください。',
    'between' => [
        'array' => ':attributeは:min個から:max個の間で指定してください。',
        'file' => ':attributeは:min KBから:max KBの間で指定してください。',
        'numeric' => ':attributeは:minから:maxの間で指定してください。',
        'string' => ':attributeは:min文字から:max文字の間で指定してください。',
    ],
    'boolean' => ':attributeはtrueかfalseを指定してください。',
    'can' => ':attributeに許可されていない値が含まれています。',
    'confirmed' => ':attributeと確認用が一致しません。',
    'contains' => ':attributeに必須の値が含まれていません。',
    'current_password' => 'パスワードが正しくありません。',
    'date' => ':attributeは有効な日付ではありません。',
    'date_equals' => ':attributeは:dateと同じ日付を指定してください。',
    'date_format' => ':attributeは:format形式で指定してください。',
    'decimal' => ':attributeは小数点以下:decimal桁で指定してください。',
    'declined' => ':attributeを拒否してください。',
    'declined_if' => ':otherが:valueの場合、:attributeを拒否してください。',
    'different' => ':attributeと:otherは異なる値を指定してください。',
    'digits' => ':attributeは:digits桁で指定してください。',
    'digits_between' => ':attributeは:min桁から:max桁の間で指定してください。',
    'dimensions' => ':attributeの画像サイズが無効です。',
    'distinct' => ':attributeに重複した値があります。',
    'doesnt_end_with' => ':attributeは次のいずれかで終わってはいけません: :values',
    'doesnt_start_with' => ':attributeは次のいずれかで始まってはいけません: :values',
    'email' => ':attributeは有効なメールアドレス形式で指定してください。',
    'ends_with' => ':attributeは次のいずれかで終わる必要があります: :values',
    'enum' => '選択された:attributeは無効です。',
    'exists' => '選択された:attributeは無効です。',
    'extensions' => ':attributeは次の拡張子のファイルである必要があります: :values',
    'file' => ':attributeはファイルを指定してください。',
    'filled' => ':attributeは必須です。',
    'gt' => [
        'array' => ':attributeは:value個より多く指定してください。',
        'file' => ':attributeは:value KBより大きいファイルを指定してください。',
        'numeric' => ':attributeは:valueより大きい値を指定してください。',
        'string' => ':attributeは:value文字より多く指定してください。',
    ],
    'gte' => [
        'array' => ':attributeは:value個以上指定してください。',
        'file' => ':attributeは:value KB以上のファイルを指定してください。',
        'numeric' => ':attributeは:value以上の値を指定してください。',
        'string' => ':attributeは:value文字以上指定してください。',
    ],
    'hex_color' => ':attributeは有効な16進数カラーコードを指定してください。',
    'image' => ':attributeは画像ファイルを指定してください。',
    'in' => '選択された:attributeは無効です。',
    'in_array' => ':attributeは:otherに存在しません。',
    'integer' => ':attributeは整数で指定してください。',
    'ip' => ':attributeは有効なIPアドレスを指定してください。',
    'ipv4' => ':attributeは有効なIPv4アドレスを指定してください。',
    'ipv6' => ':attributeは有効なIPv6アドレスを指定してください。',
    'json' => ':attributeは有効なJSON文字列を指定してください。',
    'list' => ':attributeはリストでなければなりません。',
    'lowercase' => ':attributeは小文字で指定してください。',
    'lt' => [
        'array' => ':attributeは:value個未満で指定してください。',
        'file' => ':attributeは:value KB未満のファイルを指定してください。',
        'numeric' => ':attributeは:value未満の値を指定してください。',
        'string' => ':attributeは:value文字未満で指定してください。',
    ],
    'lte' => [
        'array' => ':attributeは:value個以下で指定してください。',
        'file' => ':attributeは:value KB以下のファイルを指定してください。',
        'numeric' => ':attributeは:value以下の値を指定してください。',
        'string' => ':attributeは:value文字以下で指定してください。',
    ],
    'mac_address' => ':attributeは有効なMACアドレスを指定してください。',
    'max' => [
        'array' => ':attributeは:max個以下にしてください。',
        'file' => ':attributeは:max KB以下のファイルにしてください。',
        'numeric' => ':attributeは:max以下の値にしてください。',
        'string' => ':attributeは:max文字以下にしてください。',
    ],
    'max_digits' => ':attributeは:max桁以下にしてください。',
    'mimes' => ':attributeは次のファイル形式を指定してください: :values',
    'mimetypes' => ':attributeは次のファイル形式を指定してください: :values',
    'min' => [
        'array' => ':attributeは:min個以上指定してください。',
        'file' => ':attributeは:min KB以上のファイルを指定してください。',
        'numeric' => ':attributeは:min以上の値を指定してください。',
        'string' => ':attributeは:min文字以上入力してください。',
    ],
    'min_digits' => ':attributeは:min桁以上にしてください。',
    'missing' => ':attributeは存在してはいけません。',
    'missing_if' => ':otherが:valueの場合、:attributeは存在してはいけません。',
    'missing_unless' => ':otherが:valueでない場合、:attributeは存在してはいけません。',
    'missing_with' => ':valuesが存在する場合、:attributeは存在してはいけません。',
    'missing_with_all' => ':valuesが存在する場合、:attributeは存在してはいけません。',
    'multiple_of' => ':attributeは:valueの倍数でなければなりません。',
    'not_in' => '選択された:attributeは無効です。',
    'not_regex' => ':attributeの形式が無効です。',
    'numeric' => ':attributeは数値で指定してください。',
    'password' => [
        'letters' => ':attributeは少なくとも1文字以上の英字を含む必要があります。',
        'mixed' => ':attributeは少なくとも1文字以上の大文字と小文字を含む必要があります。',
        'numbers' => ':attributeは少なくとも1文字以上の数字を含む必要があります。',
        'symbols' => ':attributeは少なくとも1文字以上の記号を含む必要があります。',
        'uncompromised' => '指定された:attributeはデータ漏洩で発見されました。別の:attributeを選択してください。',
    ],
    'present' => ':attributeが存在していません。',
    'present_if' => ':otherが:valueの場合、:attributeが存在していません。',
    'present_unless' => ':otherが:valueでない場合、:attributeが存在していません。',
    'present_with' => ':valuesが存在する場合、:attributeが存在していません。',
    'present_with_all' => ':valuesが存在する場合、:attributeが存在していません。',
    'prohibited' => ':attributeは入力禁止です。',
    'prohibited_if' => ':otherが:valueの場合、:attributeは入力禁止です。',
    'prohibited_unless' => ':otherが:valuesにない場合、:attributeは入力禁止です。',
    'prohibits' => ':attributeが存在する場合、:otherは入力できません。',
    'regex' => ':attributeの形式が無効です。',
    'required' => ':attributeは必須項目です。',
    'required_array_keys' => ':attributeは次の項目を含む必要があります: :values',
    'required_if' => ':otherが:valueの場合、:attributeは必須項目です。',
    'required_if_accepted' => ':otherが承認された場合、:attributeは必須項目です。',
    'required_if_declined' => ':otherが拒否された場合、:attributeは必須項目です。',
    'required_unless' => ':otherが:valuesにない場合、:attributeは必須項目です。',
    'required_with' => ':valuesが指定されている場合、:attributeは必須項目です。',
    'required_with_all' => ':valuesが指定されている場合、:attributeは必須項目です。',
    'required_without' => ':valuesが指定されていない場合、:attributeは必須項目です。',
    'required_without_all' => ':valuesが1つも指定されていない場合、:attributeは必須項目です。',
    'same' => ':attributeと:otherが一致しません。',
    'size' => [
        'array' => ':attributeは:size個指定してください。',
        'file' => ':attributeは:size KBのファイルを指定してください。',
        'numeric' => ':attributeは:sizeを指定してください。',
        'string' => ':attributeは:size文字で指定してください。',
    ],
    'starts_with' => ':attributeは次のいずれかで始まる必要があります: :values',
    'string' => ':attributeは文字列を指定してください。',
    'timezone' => ':attributeは有効なタイムゾーンを指定してください。',
    'unique' => 'その:attributeはすでに使用されています。',
    'uploaded' => ':attributeのアップロードに失敗しました。',
    'uppercase' => ':attributeは大文字で指定してください。',
    'url' => ':attributeは有効なURL形式で指定してください。',
    'ulid' => ':attributeは有効なULIDを指定してください。',
    'uuid' => ':attributeは有効なUUIDを指定してください。',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'name' => '名前',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'password_confirmation' => '確認用パスワード',
        'current_password' => '現在のパスワード',
        'new_password' => '新しいパスワード',
        'token' => 'トークン',
    ],
];
