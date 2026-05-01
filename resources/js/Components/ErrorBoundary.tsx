import ErrorScreen from "@/Components/ErrorScreen";
import { Component, ErrorInfo, ReactNode } from "react";

interface Props {
    children: ReactNode;
    fallback?: ReactNode;
    resetKeys?: unknown[];
}

interface State {
    hasError: boolean;
}

function haveResetKeysChanged(
    previousResetKeys: unknown[] = [],
    nextResetKeys: unknown[] = [],
): boolean {
    if (previousResetKeys.length !== nextResetKeys.length) {
        return true;
    }

    return nextResetKeys.some((key, index) => {
        return !Object.is(key, previousResetKeys[index]);
    });
}

export default class ErrorBoundary extends Component<Props, State> {
    constructor(props: Props) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError(): State {
        return { hasError: true };
    }

    componentDidCatch(error: Error, info: ErrorInfo) {
        console.error(
            "[ErrorBoundary] レンダリングエラーを捕捉しました:",
            error.message,
        );
        console.error(
            "[ErrorBoundary] コンポーネントスタック:",
            info.componentStack,
        );
    }

    componentDidUpdate(previousProps: Props) {
        if (
            this.state.hasError &&
            haveResetKeysChanged(previousProps.resetKeys, this.props.resetKeys)
        ) {
            this.setState({ hasError: false });
        }
    }

    render() {
        if (this.state.hasError) {
            if (this.props.fallback) {
                return this.props.fallback;
            }

            return (
                <ErrorScreen
                    badge="アプリケーションエラー"
                    title="エラーが発生しました"
                    description="予期しないエラーが発生しました。ページを再読み込みするか、トップへ戻ってください。"
                    actions={
                        <>
                            <button
                                type="button"
                                onClick={() => window.location.reload()}
                                className="app-dark-cta-solid px-5 py-2 text-sm font-semibold"
                            >
                                再読み込み
                            </button>
                            <a
                                href="/"
                                className="app-dark-cta-ghost px-5 py-2 text-sm font-semibold"
                            >
                                トップへ戻る
                            </a>
                        </>
                    }
                />
            );
        }

        return this.props.children;
    }
}
