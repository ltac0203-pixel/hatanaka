import FincodePaymentForm from "@/Components/FincodePaymentForm";
import { t } from "@/i18n";
import { render } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

function mockFincode() {
    const ui: FincodeUI = {
        create: vi.fn(),
        mount: vi.fn(),
        destroy: vi.fn(),
        getFormData: vi.fn(async () => null),
    };
    const fincode: FincodeInstance = {
        ui: vi.fn(() => ui),
        tokens: vi.fn(),
    };
    const factory = vi.fn(() => fincode);

    Object.defineProperty(window, "Fincode", {
        configurable: true,
        writable: true,
        value: factory,
    });

    return { factory, fincode, ui };
}

describe("FincodePaymentForm", () => {
    afterEach(() => {
        Reflect.deleteProperty(window, "Fincode");
        vi.restoreAllMocks();
    });

    it("reports an error when the public key is empty", () => {
        const onError = vi.fn();

        render(<FincodePaymentForm publicKey=" " onError={onError} />);

        expect(onError).toHaveBeenCalledWith(
            t("fincodePayment.errorPublicKeyEmpty"),
        );
    });

    it("does not reinitialize the SDK UI when only onError changes", () => {
        const { factory, fincode, ui } = mockFincode();
        const firstOnError = vi.fn();
        const secondOnError = vi.fn();

        const { rerender, unmount } = render(
            <FincodePaymentForm
                publicKey="p_test_12345"
                onError={firstOnError}
            />,
        );

        expect(factory).toHaveBeenCalledTimes(1);
        expect(fincode.ui).toHaveBeenCalledTimes(1);
        expect(ui.create).toHaveBeenCalledWith("payments", expect.any(Object));
        expect(ui.mount).toHaveBeenCalledWith(expect.any(String), "420");

        rerender(
            <FincodePaymentForm
                publicKey="p_test_12345"
                onError={secondOnError}
            />,
        );

        expect(factory).toHaveBeenCalledTimes(1);
        expect(fincode.ui).toHaveBeenCalledTimes(1);
        expect(ui.mount).toHaveBeenCalledTimes(1);
        expect(ui.destroy).not.toHaveBeenCalled();

        unmount();

        expect(ui.destroy).toHaveBeenCalledTimes(1);
    });
});
