const fs = require("fs");
const path = require("path");
const parser = require("@babel/parser");
const traverse = require("@babel/traverse").default;
const MagicString = require("magic-string");

const root = path.resolve(__dirname, "..");
const sourceRoot = path.join(root, "resources", "js");
const files = [];
const changed = [];
const skipped = [];

function walk(directory) {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
        const file = path.join(directory, entry.name);
        if (entry.isDirectory()) walk(file);
        else if (/\.(jsx|tsx)$/.test(entry.name)) files.push(file);
    }
}

function identifierName(node) {
    return node?.type === "JSXIdentifier" ? node.name : null;
}

walk(sourceRoot);

for (const file of files) {
    const source = fs.readFileSync(file, "utf8");
    let ast;
    try {
        ast = parser.parse(source, {
            sourceType: "module",
            plugins: ["jsx", "typescript"],
        });
    } catch {
        continue;
    }

    const replacements = [];
    let alreadyImported = false;
    let uiImport = null;

    traverse(ast, {
        ImportDeclaration(importPath) {
            const value = importPath.node.source.value.replaceAll("\\", "/");
            if (!value.endsWith("/Components/UI")) return;
            uiImport = importPath.node;
            alreadyImported ||= importPath.node.specifiers.some(
                (specifier) => specifier.imported?.name === "TableActions",
            );
        },
        JSXElement(elementPath) {
            if (identifierName(elementPath.node.openingElement.name) !== "td")
                return;

            let actionCount = 0;
            let hasTableActions = false;
            elementPath.traverse({
                JSXOpeningElement(childPath) {
                    const name = identifierName(childPath.node.name);
                    if (
                        [
                            "button",
                            "a",
                            "Button",
                            "Link",
                            "IconButton",
                        ].includes(name)
                    )
                        actionCount += 1;
                    if (name === "TableActions") hasTableActions = true;
                },
            });
            const minimumActions = source.includes("Aksi") ? 1 : 2;
            if (actionCount < minimumActions || hasTableActions) return;

            const roots = elementPath.node.children.filter(
                (child) =>
                    child.type === "JSXElement" ||
                    (child.type === "JSXExpressionContainer" &&
                        child.expression.type !== "JSXEmptyExpression"),
            );
            const row = elementPath.parentPath?.node;
            const cells =
                row?.type === "JSXElement"
                    ? row.children.filter(
                          (child) =>
                              child.type === "JSXElement" &&
                              identifierName(child.openingElement.name) ===
                                  "td",
                      )
                    : [];
            const isLastCell =
                cells.length > 0 && cells.at(-1) === elementPath.node;

            if (roots.length === 1 && isLastCell) {
                const rootNode = roots[0];
                const directName =
                    rootNode.type === "JSXElement"
                        ? identifierName(rootNode.openingElement.name)
                        : null;
                if (
                    ["Button", "Link", "button", "IconButton"].includes(
                        directName,
                    ) ||
                    rootNode.type === "JSXExpressionContainer"
                ) {
                    replacements.push({
                        wrapEnd: rootNode.end,
                        wrapStart: rootNode.start,
                    });
                    return;
                }
            }
            if (roots.length !== 1 || roots[0].type !== "JSXElement") {
                skipped.push({
                    file: path.relative(root, file),
                    line: elementPath.node.loc.start.line,
                    actions: actionCount,
                    roots: roots.map((child) =>
                        child.type === "JSXElement"
                            ? identifierName(child.openingElement.name)
                            : child.expression.type,
                    ),
                });
                return;
            }

            const wrapper = roots[0];
            if (
                identifierName(wrapper.openingElement.name) !== "div" ||
                !wrapper.closingElement
            ) {
                skipped.push({
                    file: path.relative(root, file),
                    line: elementPath.node.loc.start.line,
                    actions: actionCount,
                    roots: [identifierName(wrapper.openingElement.name)],
                });
                return;
            }
            replacements.push({
                closeEnd: wrapper.closingElement.end,
                closeStart: wrapper.closingElement.start,
                openEnd: wrapper.openingElement.end,
                openStart: wrapper.openingElement.start,
            });
        },
    });

    if (!replacements.length) continue;
    const output = new MagicString(source);
    for (const replacement of replacements.sort(
        (a, b) => (b.openStart ?? b.wrapStart) - (a.openStart ?? a.wrapStart),
    )) {
        if (replacement.wrapStart !== undefined) {
            output.appendRight(replacement.wrapEnd, "</TableActions>");
            output.appendLeft(replacement.wrapStart, "<TableActions>");
            continue;
        }
        output.overwrite(
            replacement.closeStart,
            replacement.closeEnd,
            "</TableActions>",
        );
        output.overwrite(
            replacement.openStart,
            replacement.openEnd,
            "<TableActions>",
        );
    }

    if (!alreadyImported) {
        if (
            uiImport &&
            uiImport.specifiers.every(
                (specifier) => specifier.type === "ImportSpecifier",
            )
        ) {
            const closingBrace = source.lastIndexOf("}", uiImport.end);
            const beforeBrace = source.slice(uiImport.start, closingBrace);
            const separator =
                uiImport.specifiers.length &&
                !beforeBrace.trimEnd().endsWith(",")
                    ? ","
                    : "";
            output.appendLeft(closingBrace, `${separator} TableActions`);
        } else {
            let relative = path.relative(
                path.dirname(file),
                path.join(sourceRoot, "Components", "UI"),
            );
            relative = relative.replaceAll("\\", "/");
            if (!relative.startsWith(".")) relative = `./${relative}`;
            output.prepend(`import { TableActions } from "${relative}";\n`);
        }
    }

    fs.writeFileSync(file, output.toString());
    changed.push(path.relative(root, file));
}

console.log(
    JSON.stringify({ changed, count: changed.length, skipped }, null, 2),
);
