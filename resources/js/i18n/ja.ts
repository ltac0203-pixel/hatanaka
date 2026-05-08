/**
 * 日本語翻訳定数
 * フロントエンドUIの中央管理
 */

export const ja = {
    auth: {
        shared: {
            eyebrow: "認証",
            heroDescription:
                "アカウント登録からカード登録、サブスクリプションの運用までを一つの導線で管理できます。",
        },
        login: {
            title: "ログイン",
            description:
                "登録済みアカウントでサインインして、契約状況や決済設定を確認します。",
            emailLabel: "メールアドレス",
            passwordLabel: "パスワード",
            rememberMe: "ログイン状態を保持する",
            loginButton: "ログイン",
            noAccount: "アカウントをお持ちでないですか?",
            registerLink: "新規登録",
        },
        register: {
            title: "新規登録",
            description:
                "新しいアカウントを作成して、サブスクリプション管理を開始します。",
            nameLabel: "名前",
            emailLabel: "メールアドレス",
            passwordLabel: "パスワード",
            confirmPasswordLabel: "確認用パスワード",
            alreadyRegistered: "すでに登録済みですか？",
            loginLink: "ログイン",
            registerButton: "登録",
        },
        verifyEmail: {
            title: "メールアドレスの確認",
            description:
                "ご登録ありがとうございます！開始する前に、メールアドレスを確認していただく必要があります。登録時に入力されたメールアドレスにリンクを送信しましたので、メールをご確認ください。メールが届いていない場合は、再送信いたします。",
            linkSent: "新しい確認リンクをメールアドレスに送信しました。",
            resendButton: "確認メールを再送信",
            logoutButton: "ログアウト",
        },
    },
    profile: {
        title: "プロフィール",
        updateProfileInformation: {
            title: "プロフィール情報",
            description:
                "アカウントのプロフィール情報とメールアドレスを更新します。",
            nameLabel: "名前",
            emailLabel: "メールアドレス",
            emailUnverified: "メールアドレスが未確認です。",
            resendVerification:
                "確認メールを再送信するには、ここをクリックしてください。",
            verificationSent:
                "新しい確認リンクをメールアドレスに送信しました。",
            saveButton: "保存",
            saved: "保存しました。",
        },
        deleteUser: {
            title: "アカウント削除",
            description:
                "アカウントを削除すると、すべてのリソースとデータが完全に削除されます。アカウントを削除する前に、保持したいデータや情報をダウンロードしてください。",
            deleteButton: "アカウントを削除",
            modalTitle: "アカウントを削除してもよろしいですか？",
            modalDescription:
                "アカウントを削除すると、すべてのリソースとデータが完全に削除されます。アカウントを完全に削除することを確認するため、パスワードを入力してください。",
            passwordLabel: "パスワード",
            cancelButton: "キャンセル",
            confirmButton: "アカウントを削除",
        },
        logout: {
            title: "ログアウト",
            description: "このアカウントからログアウトします。",
            logoutButton: "ログアウト",
        },
    },
    common: {
        save: "保存",
        delete: "削除",
        cancel: "キャンセル",
        confirm: "確認",
        close: "閉じる",
        saved: "保存しました。",
        execute: "実行",
        processing: "処理中...",
        loading: "読み込み中...",
        back: "戻る",
    },
    welcome: {
        title: "ようこそ",
        appName: "サブスクリプション管理",
        dashboard: "ダッシュボード",
        login: "ログイン",
        register: "新規登録",
        betaBadge: "これはテスト段階です。",
        tagline1: "シンプルで安全なサブスクリプション管理",
        tagline2: "Fincode決済でスムーズな運用を",
        feature1: {
            title: "定期課金管理",
            description:
                "プランの作成・変更・解約をワンクリックで。柔軟なサブスクリプション管理を実現します。",
        },
        feature2: {
            title: "安全な決済",
            description:
                "Fincode APIによるトークン化で、カード情報をサーバーに送信せずに安全に決済できます。",
        },
    },
    dashboard: {
        title: "ダッシュボード",
        welcome: "ようこそ!",
        loginSuccess:
            "ログインに成功しました。ここからアプリケーションの機能を利用できます。",
        subscription: {
            label: "サブスクリプション",
            description: "契約状況の確認・管理",
        },
        plan: {
            label: "プラン",
            description: "利用可能なプラン一覧",
        },
        card: {
            label: "カード",
            description: "決済カードの登録・管理",
        },
    },
    plan: {
        listTitle: "プラン一覧",
        detailTitle: "プラン詳細",
        selectButton: "このプランを選択",
        features: "プラン特典",
        subscriptionSection: "サブスクリプション登録",
        hasActiveSubscription:
            "既にアクティブなサブスクリプションがあります。新しいプランに変更するには、現在のサブスクリプションを解約してください。",
        goToSubscription: "サブスクリプション管理へ",
        noCard: "サブスクリプションを登録するには、まずカードを登録してください。",
        registerCard: "カードを登録",
    },
    subscriptionForm: {
        cardSelectLabel: "決済カードを選択",
        startDateLabel: "開始日",
        expiryPrefix: "有効期限:",
        defaultSuffix: "(デフォルト)",
        backToPlans: "プラン一覧に戻る",
        registerButton: "サブスクリプションを登録",
    },
    subscription: {
        title: "サブスクリプション管理",
        currentSection: "現在のサブスクリプション",
        plan: "プラン",
        price: "価格",
        status: "ステータス",
        nextChargeDate: "次回課金日",
        paymentCard: "決済カード",
        cancelButton: "サブスクリプションを解約",
        noSubscription: "現在、サブスクリプションに登録されていません。",
        selectPlan: "プランを選択",
        cancelDialog: {
            title: "サブスクリプション解約",
            message: "本当にサブスクリプションを解約しますか？",
            confirmLabel: "解約する",
        },
        statusLabels: {
            active: "有効",
            canceled: "解約済み",
            expired: "期限切れ",
            unpaid: "未払い",
            incomplete: "未完了",
        },
        cardsSection: "登録済みカード",
        addCard: "カードを追加",
        cardExpiry: "有効期限:",
        cardDefault: "デフォルト",
        noCards: "登録されているカードがありません。",
        deleteCardDialog: {
            title: "カード削除",
            message: "本当にカードを削除しますか？",
            confirmLabel: "削除する",
        },
        errors: {
            cancelFailed:
                "サブスクリプションの解約に失敗しました。時間をおいて再試行してください。",
            cardDeleteFailed:
                "カードの削除に失敗しました。時間をおいて再試行してください。",
        },
    },
    card: {
        registerTitle: "カード登録",
        setAsDefault: "このカードをデフォルトに設定",
        securityNote:
            "カード情報は安全に暗号化されます。カード番号やセキュリティコードはサーバーに送信されません。",
        submitButton: "カードを登録",
        loadingButton: "フォーム読み込み中...",
        processingButton: "処理中...",
        loading: "決済フォームを読み込み中です...",
        sdkNotReady:
            "決済フォームを読み込み中です。少し待ってから再試行してください。",
        tokenizeError:
            "カード情報のトークン化に失敗しました。カード情報を確認してください。",
    },
    error: {
        forbidden: {
            title: "アクセス拒否",
            description: "このページへアクセスする権限がありません。",
        },
        notFound: {
            title: "ページが見つかりません",
            description: "指定されたページは見つかりませんでした。",
        },
        rateLimit: {
            title: "リクエスト過多",
            description:
                "アクセスが集中しています。しばらく待ってから再度お試しください。",
        },
        serverError: {
            title: "サーバーエラー",
            description: "システムで予期しないエラーが発生しました。",
        },
        unavailable: {
            title: "サービス利用不可",
            description:
                "現在サービスが利用できません。しばらくしてから再度お試しください。",
        },
        timeout: {
            title: "タイムアウト",
            description:
                "外部サービスの応答がタイムアウトしました。しばらくしてから再度お試しください。",
        },
        backToTop: "トップへ戻る",
        backToPrev: "前の画面に戻る",
    },
    nav: {
        dashboard: "ダッシュボード",
        subscription: "サブスクリプション",
        plan: "プラン",
        card: "カード",
        profile: "プロフィール",
        logout: "ログアウト",
    },
    fincodePayment: {
        labelCardNo: "カード番号",
        labelExpire: "有効期限",
        labelCvc: "CVC",
        errorPublicKeyEmpty:
            "決済設定エラー: FINCODE_PUBLIC_KEY が未設定です。運用管理者にお問い合わせください。",
        errorPublicKeyInvalid:
            "決済設定エラー: FINCODE_PUBLIC_KEY の形式が不正です。運用管理者にお問い合わせください。",
        errorSdkNotLoaded:
            "決済SDKの読み込みに失敗しました。ページを再読み込みしてください。",
        errorInitFailed:
            "決済フォームの初期化に失敗しました。ページを再読み込みしてください。",
        errorFormNotInit: "決済フォームが初期化されていません。",
        errorFormDataFailed: "カード情報の取得に失敗しました。",
        errorPayloadFailed:
            "カード情報の取得に失敗しました。入力内容を確認してください。",
        errorTokenFailed: "トークンの取得に失敗しました。",
    },
    fincodeSdk: {
        errorInvalidDomain:
            "決済SDKのURLが許可されていないドメインです。運用管理者にお問い合わせください。",
        errorLoadFailed:
            "決済SDKの読み込みに失敗しました。ページを再読み込みしてください。",
    },
} as const;
