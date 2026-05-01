import tsParser from "@typescript-eslint/parser";
import reactHooks from "eslint-plugin-react-hooks";
import globals from "globals";

const MUTATING_ARRAY_METHODS = new Set([
    "copyWithin",
    "fill",
    "pop",
    "push",
    "reverse",
    "shift",
    "sort",
    "splice",
    "unshift",
]);

function addPatternBindings(pattern, addBinding) {
    if (!pattern) {
        return;
    }

    switch (pattern.type) {
        case "Identifier":
            addBinding(pattern.name);
            break;
        case "ArrayPattern":
            pattern.elements.forEach((element) =>
                addPatternBindings(element, addBinding),
            );
            break;
        case "ObjectPattern":
            pattern.properties.forEach((property) => {
                if (property.type === "Property") {
                    addPatternBindings(property.value, addBinding);
                    return;
                }

                addPatternBindings(property.argument, addBinding);
            });
            break;
        case "AssignmentPattern":
            addPatternBindings(pattern.left, addBinding);
            break;
        case "RestElement":
            addPatternBindings(pattern.argument, addBinding);
            break;
    }
}

function unwrapExpression(node) {
    if (!node) {
        return null;
    }

    switch (node.type) {
        case "ChainExpression":
            return unwrapExpression(node.expression);
        case "TSAsExpression":
        case "TSSatisfiesExpression":
        case "TSTypeAssertion":
            return unwrapExpression(node.expression);
        default:
            return node;
    }
}

function getAssignedFunctionName(node) {
    if (!node.parent) {
        return null;
    }

    if (
        node.parent.type === "VariableDeclarator" &&
        node.parent.id.type === "Identifier"
    ) {
        return node.parent.id.name;
    }

    if (
        node.parent.type === "AssignmentExpression" &&
        node.parent.left.type === "Identifier"
    ) {
        return node.parent.left.name;
    }

    return null;
}

function getFunctionName(node) {
    if (
        (node.type === "FunctionDeclaration" ||
            node.type === "FunctionExpression") &&
        node.id
    ) {
        return node.id.name;
    }

    return getAssignedFunctionName(node);
}

function isComponentOrHookFunction(node) {
    const name = getFunctionName(node);
    return Boolean(name && (/^[A-Z]/.test(name) || /^use[A-Z]/.test(name)));
}

function isFunctionNode(node) {
    return Boolean(
        node &&
        (node.type === "FunctionDeclaration" ||
            node.type === "FunctionExpression" ||
            node.type === "ArrowFunctionExpression"),
    );
}

function isReducerFunction(node) {
    const name = getFunctionName(node);
    return Boolean(name && /Reducer$/.test(name));
}

function isHookCall(node, hookName) {
    const expression = unwrapExpression(node);
    return (
        expression?.type === "CallExpression" &&
        expression.callee.type === "Identifier" &&
        expression.callee.name === hookName
    );
}

function isUsePageCall(node) {
    const expression = unwrapExpression(node);
    return (
        expression?.type === "CallExpression" &&
        expression.callee.type === "Identifier" &&
        expression.callee.name === "usePage"
    );
}

function getMemberPropertyName(node) {
    if (node.computed || node.property.type !== "Identifier") {
        return null;
    }

    return node.property.name;
}

function hasUsePagePropsRoot(node) {
    let current = unwrapExpression(node);

    while (current?.type === "MemberExpression") {
        if (
            getMemberPropertyName(current) === "props" &&
            isUsePageCall(current.object)
        ) {
            return true;
        }

        current = unwrapExpression(current.object);
    }

    return false;
}

function getRootIdentifierName(node) {
    const expression = unwrapExpression(node);

    if (!expression) {
        return null;
    }

    if (expression.type === "Identifier") {
        return expression.name;
    }

    if (expression.type === "MemberExpression") {
        return getRootIdentifierName(expression.object);
    }

    return null;
}

function isMapCallExpression(node) {
    const expression = unwrapExpression(node);

    return (
        expression?.type === "CallExpression" &&
        expression.callee.type === "MemberExpression" &&
        getMemberPropertyName(expression.callee) === "map"
    );
}

function createSnapshotMutationRule() {
    return {
        meta: {
            type: "problem",
            docs: {
                description:
                    "Disallow direct mutation of React props, state snapshots, and Inertia page props.",
            },
            schema: [],
            messages: {
                noDirectMutation:
                    "Do not mutate {{target}} directly. {{reason}} Create a copy and update that copy instead.",
                noMutatingMethod:
                    "Do not call mutating array method '{{method}}' on {{target}}. {{reason}} Create a copied array before updating it.",
                noObjectAssign:
                    "Do not mutate {{target}} with Object.assign. {{reason}} Create a copied object instead.",
            },
        },
        create(context) {
            const functionStack = [];

            function getCurrentFunctionContext() {
                return functionStack[functionStack.length - 1] ?? null;
            }

            function resolveImmutableReason(name) {
                for (
                    let index = functionStack.length - 1;
                    index >= 0;
                    index -= 1
                ) {
                    const functionContext = functionStack[index];

                    if (functionContext.immutableBindings.has(name)) {
                        return functionContext.immutableBindings.get(name);
                    }

                    if (functionContext.declaredBindings.has(name)) {
                        return null;
                    }
                }

                return null;
            }

            function markBindingsAsDeclared(pattern) {
                const functionContext = getCurrentFunctionContext();

                if (!functionContext) {
                    return;
                }

                addPatternBindings(pattern, (name) =>
                    functionContext.declaredBindings.add(name),
                );
            }

            function markBindingsAsImmutable(pattern, reason) {
                const functionContext = getCurrentFunctionContext();

                if (!functionContext) {
                    return;
                }

                addPatternBindings(pattern, (name) =>
                    functionContext.immutableBindings.set(name, reason),
                );
            }

            function getImmutableReasonForExpression(node) {
                if (!node) {
                    return null;
                }

                if (hasUsePagePropsRoot(node)) {
                    return "Inertia page props are immutable snapshots.";
                }

                const rootIdentifierName = getRootIdentifierName(node);
                if (!rootIdentifierName) {
                    return null;
                }

                return resolveImmutableReason(rootIdentifierName);
            }

            function getMutationTarget(node) {
                const expression = unwrapExpression(node);

                if (!expression || expression.type !== "MemberExpression") {
                    return null;
                }

                const reason = getImmutableReasonForExpression(
                    expression.object,
                );
                if (!reason) {
                    return null;
                }

                const rootIdentifierName = getRootIdentifierName(
                    expression.object,
                );
                if (!rootIdentifierName) {
                    return null;
                }

                return {
                    reason,
                    target: rootIdentifierName,
                };
            }

            function enterFunction(node) {
                const functionContext = {
                    declaredBindings: new Set(),
                    immutableBindings: new Map(),
                };

                functionStack.push(functionContext);

                node.params.forEach((param) => {
                    addPatternBindings(param, (name) =>
                        functionContext.declaredBindings.add(name),
                    );
                });

                const firstParam = node.params[0];
                if (!firstParam) {
                    return;
                }

                if (isComponentOrHookFunction(node)) {
                    addPatternBindings(firstParam, (name) =>
                        functionContext.immutableBindings.set(
                            name,
                            "Props and hook inputs are immutable snapshots.",
                        ),
                    );
                    return;
                }

                if (isReducerFunction(node)) {
                    addPatternBindings(firstParam, (name) =>
                        functionContext.immutableBindings.set(
                            name,
                            "Reducer state must be treated as immutable.",
                        ),
                    );
                }
            }

            function exitFunction() {
                functionStack.pop();
            }

            return {
                FunctionDeclaration(node) {
                    const functionContext = getCurrentFunctionContext();
                    if (functionContext && node.id) {
                        functionContext.declaredBindings.add(node.id.name);
                    }

                    enterFunction(node);
                },
                "FunctionDeclaration:exit": exitFunction,
                FunctionExpression: enterFunction,
                "FunctionExpression:exit": exitFunction,
                ArrowFunctionExpression: enterFunction,
                "ArrowFunctionExpression:exit": exitFunction,
                VariableDeclarator(node) {
                    markBindingsAsDeclared(node.id);

                    if (
                        node.id.type === "ArrayPattern" &&
                        (isHookCall(node.init, "useState") ||
                            isHookCall(node.init, "useReducer"))
                    ) {
                        markBindingsAsImmutable(
                            node.id.elements[0],
                            "State values returned by React hooks are immutable snapshots.",
                        );
                        return;
                    }

                    const reason = getImmutableReasonForExpression(node.init);
                    if (!reason) {
                        return;
                    }

                    markBindingsAsImmutable(node.id, reason);
                },
                AssignmentExpression(node) {
                    const mutationTarget = getMutationTarget(node.left);
                    if (!mutationTarget) {
                        return;
                    }

                    context.report({
                        node,
                        messageId: "noDirectMutation",
                        data: mutationTarget,
                    });
                },
                UpdateExpression(node) {
                    const mutationTarget = getMutationTarget(node.argument);
                    if (!mutationTarget) {
                        return;
                    }

                    context.report({
                        node,
                        messageId: "noDirectMutation",
                        data: mutationTarget,
                    });
                },
                UnaryExpression(node) {
                    if (node.operator !== "delete") {
                        return;
                    }

                    const mutationTarget = getMutationTarget(node.argument);
                    if (!mutationTarget) {
                        return;
                    }

                    context.report({
                        node,
                        messageId: "noDirectMutation",
                        data: mutationTarget,
                    });
                },
                CallExpression(node) {
                    const expression = unwrapExpression(node.callee);

                    if (
                        expression?.type === "MemberExpression" &&
                        MUTATING_ARRAY_METHODS.has(
                            getMemberPropertyName(expression) ?? "",
                        )
                    ) {
                        const reason = getImmutableReasonForExpression(
                            expression.object,
                        );
                        const target = getRootIdentifierName(expression.object);

                        if (!reason || !target) {
                            return;
                        }

                        context.report({
                            node,
                            messageId: "noMutatingMethod",
                            data: {
                                method: getMemberPropertyName(expression),
                                reason,
                                target,
                            },
                        });
                        return;
                    }

                    if (
                        expression?.type === "MemberExpression" &&
                        expression.object.type === "Identifier" &&
                        expression.object.name === "Object" &&
                        getMemberPropertyName(expression) === "assign" &&
                        node.arguments.length > 0
                    ) {
                        const reason = getImmutableReasonForExpression(
                            node.arguments[0],
                        );
                        const target = getRootIdentifierName(node.arguments[0]);

                        if (!reason || !target) {
                            return;
                        }

                        context.report({
                            node,
                            messageId: "noObjectAssign",
                            data: {
                                reason,
                                target,
                            },
                        });
                    }
                },
            };
        },
    };
}

function createStableReactKeyRule() {
    return {
        meta: {
            type: "problem",
            docs: {
                description:
                    "Disallow array indexes and unstable values in React keys.",
            },
            schema: [],
            messages: {
                noArrayIndex:
                    "Do not use array index '{{name}}' as a React key. Use a stable item identifier instead.",
                noUnstableValue:
                    "Do not use unstable values such as Math.random(), Date.now(), or crypto.randomUUID() for React keys.",
            },
        },
        create(context) {
            const sourceCode = context.sourceCode ?? context.getSourceCode();

            function findVariable(scope, name) {
                let currentScope = scope;

                while (currentScope) {
                    const variable = currentScope.set.get(name);
                    if (variable) {
                        return variable;
                    }

                    currentScope = currentScope.upper;
                }

                return null;
            }

            function isMapCallbackFunction(node) {
                return Boolean(
                    isFunctionNode(node) &&
                    node.parent?.type === "CallExpression" &&
                    isMapCallExpression(node.parent) &&
                    node.parent.arguments[0] === node,
                );
            }

            function isMapIndexIdentifier(node) {
                if (node.type !== "Identifier") {
                    return false;
                }

                const variable = findVariable(
                    sourceCode.getScope(node),
                    node.name,
                );

                if (!variable) {
                    return false;
                }

                return variable.defs.some((definition) => {
                    if (
                        definition.type !== "Parameter" ||
                        !isFunctionNode(definition.node) ||
                        !isMapCallbackFunction(definition.node)
                    ) {
                        return false;
                    }

                    const indexBindings = new Set();
                    addPatternBindings(definition.node.params[1], (name) =>
                        indexBindings.add(name),
                    );

                    return indexBindings.has(variable.name);
                });
            }

            function expressionContains(node, predicate) {
                const stack = [node];

                while (stack.length > 0) {
                    const current = stack.pop();

                    if (!current || typeof current !== "object") {
                        continue;
                    }

                    if (predicate(current)) {
                        return true;
                    }

                    for (const [key, value] of Object.entries(current)) {
                        if (key === "parent") {
                            continue;
                        }

                        if (Array.isArray(value)) {
                            value.forEach((child) => {
                                if (child && typeof child.type === "string") {
                                    stack.push(child);
                                }
                            });
                            continue;
                        }

                        if (value && typeof value.type === "string") {
                            stack.push(value);
                        }
                    }
                }

                return false;
            }

            function isCryptoObject(node) {
                const expression = unwrapExpression(node);

                return Boolean(
                    (expression?.type === "Identifier" &&
                        expression.name === "crypto") ||
                    (expression?.type === "MemberExpression" &&
                        getMemberPropertyName(expression) === "crypto"),
                );
            }

            function isUnstableKeyCall(node) {
                if (node.type !== "CallExpression") {
                    return false;
                }

                const callee = unwrapExpression(node.callee);
                if (callee?.type !== "MemberExpression") {
                    return false;
                }

                const propertyName = getMemberPropertyName(callee);

                if (
                    callee.object.type === "Identifier" &&
                    callee.object.name === "Math" &&
                    propertyName === "random"
                ) {
                    return true;
                }

                if (
                    callee.object.type === "Identifier" &&
                    callee.object.name === "Date" &&
                    propertyName === "now"
                ) {
                    return true;
                }

                return (
                    propertyName === "randomUUID" &&
                    isCryptoObject(callee.object)
                );
            }

            return {
                JSXAttribute(node) {
                    if (
                        node.name.type !== "JSXIdentifier" ||
                        node.name.name !== "key" ||
                        node.value?.type !== "JSXExpressionContainer"
                    ) {
                        return;
                    }

                    const keyExpression = node.value.expression;
                    const containsMapIndex = expressionContains(
                        keyExpression,
                        isMapIndexIdentifier,
                    );

                    if (containsMapIndex) {
                        const arrayIndexIdentifier =
                            sourceCode.getText(keyExpression);

                        context.report({
                            node,
                            messageId: "noArrayIndex",
                            data: {
                                name: arrayIndexIdentifier,
                            },
                        });
                        return;
                    }

                    if (expressionContains(keyExpression, isUnstableKeyCall)) {
                        context.report({
                            node,
                            messageId: "noUnstableValue",
                        });
                    }
                },
            };
        },
    };
}

function createTrivialManualMemoizationRule() {
    return {
        meta: {
            type: "suggestion",
            docs: {
                description:
                    "Warn when components or hooks use trivial manual memoization with empty dependency arrays.",
            },
            schema: [],
            messages: {
                avoidTrivialMemo:
                    "Avoid {{hookName}} with an empty dependency array for local values. Keep rendering pure and remove unnecessary Effects before adding manual memoization.",
            },
        },
        create(context) {
            function findNearestFunction(node) {
                let current = node.parent;

                while (current) {
                    if (isFunctionNode(current)) {
                        return current;
                    }

                    current = current.parent;
                }

                return null;
            }

            return {
                CallExpression(node) {
                    const expression = unwrapExpression(node.callee);
                    if (
                        expression?.type !== "Identifier" ||
                        (expression.name !== "useCallback" &&
                            expression.name !== "useMemo")
                    ) {
                        return;
                    }

                    const dependencyArray = node.arguments[1];
                    if (
                        dependencyArray?.type !== "ArrayExpression" ||
                        dependencyArray.elements.length !== 0
                    ) {
                        return;
                    }

                    const owner = findNearestFunction(node);
                    if (!owner || !isComponentOrHookFunction(owner)) {
                        return;
                    }

                    context.report({
                        node,
                        messageId: "avoidTrivialMemo",
                        data: {
                            hookName: expression.name,
                        },
                    });
                },
            };
        },
    };
}

export default [
    {
        ignores: [
            "node_modules/**",
            "public/build/**",
            "vendor/**",
            "resources/js/**/*.d.ts",
        ],
    },
    {
        files: ["resources/js/**/*.{ts,tsx}"],
        languageOptions: {
            parser: tsParser,
            ecmaVersion: "latest",
            sourceType: "module",
            parserOptions: {
                ecmaFeatures: {
                    jsx: true,
                },
            },
            globals: {
                ...globals.browser,
            },
        },
        plugins: {
            "react-hooks": reactHooks,
            snapshot: {
                rules: {
                    "no-direct-mutation": createSnapshotMutationRule(),
                },
            },
            "stable-keys": {
                rules: {
                    "no-unstable-react-key": createStableReactKeyRule(),
                },
            },
            "react-design": {
                rules: {
                    "no-trivial-manual-memoization":
                        createTrivialManualMemoizationRule(),
                },
            },
        },
        rules: {
            "react-hooks/rules-of-hooks": "error",
            "react-hooks/exhaustive-deps": "warn",
            "react-hooks/immutability": "error",
            "snapshot/no-direct-mutation": "error",
            "stable-keys/no-unstable-react-key": "error",
            "react-design/no-trivial-manual-memoization": "warn",
        },
    },
];
