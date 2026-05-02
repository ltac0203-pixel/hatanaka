declare global {
    interface FincodeCallback<T = unknown> {
        (status: number, response: T): void;
    }

    interface FincodeErrorCallback {
        (message: string): void;
    }

    interface FincodeUIAppearance {
        layout?: "vertical" | "horizontal";
        hideLabel?: boolean;
        hideHolderName?: boolean;
        hidePayTimes?: boolean;
        labelCardNo?: string;
        labelExpire?: string;
        labelCvc?: string;
        labelHolderName?: string;
        // 入力例をSDK側へ渡し、フォームの期待値を利用者へ伝える。
        cardNo?: string;
        expireMonth?: string;
        expireYear?: string;
        cvc?: string;
        holderName?: string;
        // SDK 埋め込みUIでも既存デザインと色調を揃えられるようにする。
        colorBackground?: string;
        colorBackgroundInput?: string;
        colorText?: string;
        colorPlaceHolder?: string;
        colorLabelText?: string;
        colorBorder?: string;
        colorError?: string;
        colorCheck?: string;
        theme?: string;
    }

    interface FincodeUI {
        create(
            type: "payments" | "payment",
            appearance?: FincodeUIAppearance,
            callBack?: FincodeCallback,
            errorCallBack?: FincodeErrorCallback,
        ): void;
        mount(
            elementId: string,
            width?: string,
            callBack?: FincodeCallback,
            errorCallBack?: FincodeErrorCallback,
        ): void;
        destroy(
            callBack?: FincodeCallback,
            errorCallBack?: FincodeErrorCallback,
        ): void;
        getFormData(): Promise<Record<string, unknown> | null>;
    }

    interface FincodeApiErrorItem {
        error_code?: string;
        error_message?: string;
        [key: string]: unknown;
    }

    interface FincodeTokenCardPayload {
        card_no: string;
        // Fincode tokens API は expire を YYMM (4 桁) で要求する。
        expire: string;
        holder_name?: string;
        security_code?: string;
    }

    interface FincodeTokenResponse {
        list?: Array<{
            token?: string;
            cardNo?: string;
            card_no?: string;
            expire?: string;
            holderName?: string;
            holder_name?: string;
        }>;
        errors?: FincodeApiErrorItem[];
        [key: string]: unknown;
    }

    interface FincodeInstance {
        ui(appearance: FincodeUIAppearance): FincodeUI;
        tokens(
            card: FincodeTokenCardPayload,
            callBack: FincodeCallback<FincodeTokenResponse>,
            errorCallBack?: FincodeErrorCallback,
        ): void;
    }

    interface Window {
        Fincode: (publicKey: string) => FincodeInstance;
    }
}

export {};
