import { useEditor, EditorContent } from "@tiptap/react";
import StarterKit from "@tiptap/starter-kit";
import Underline from "@tiptap/extension-underline";
import TextAlign from "@tiptap/extension-text-align";
import { Table, TableView } from "@tiptap/extension-table";
import TableRow from "@tiptap/extension-table-row";
import TableHeader from "@tiptap/extension-table-header";
import TableCell from "@tiptap/extension-table-cell";
import { TextStyle } from "@tiptap/extension-text-style";
import FontFamily from "@tiptap/extension-font-family";
import Color from "@tiptap/extension-color";
import Highlight from "@tiptap/extension-highlight";
import Image from "@tiptap/extension-image";
import { useEffect, useRef, useState } from "react";
import {
    Bold,
    Italic,
    Underline as UnderlineIcon,
    Strikethrough,
    Undo2,
    Redo2,
    AlignLeft,
    AlignCenter,
    AlignRight,
    AlignJustify,
    List,
    ListOrdered,
    Table2,
    Minus,
    Pilcrow,
    ImagePlus,
    PenLine,
    Rows3,
    Columns3,
    Merge,
    Split,
    Trash2,
} from "lucide-react";

class DocumentTableView extends TableView {
    constructor(node, cellMinWidth, view, HTMLAttributes) {
        super(node, cellMinWidth, view, HTMLAttributes);
        this.applyLayout(node);
    }
    update(node) {
        const result = super.update(node);
        if (result) this.applyLayout(node);
        return result;
    }
    applyLayout(node) {
        const width = node.attrs.tableWidth || "100%",
            align = node.attrs.tableAlign || "left";
        this.table.style.width = width;
        this.table.dataset.width = width;
        this.table.dataset.align = align;
        this.dom.style.display = "flex";
        this.dom.style.width = "100%";
        this.dom.style.justifyContent =
            align === "center"
                ? "center"
                : align === "right"
                  ? "flex-end"
                  : "flex-start";
    }
}
const EnhancedTable = Table.extend({
    addAttributes() {
        return {
            ...(this.parent?.() || {}),
            tableWidth: {
                default: "100%",
                parseHTML: (e) =>
                    e.style.width || e.getAttribute("data-width") || "100%",
                renderHTML: (a) => ({
                    "data-width": a.tableWidth,
                    style: `width:${a.tableWidth}`,
                }),
            },
            tableAlign: {
                default: "left",
                parseHTML: (e) => e.getAttribute("data-align") || "left",
                renderHTML: (a) => ({ "data-align": a.tableAlign }),
            },
        };
    },
});
const EnhancedRow = TableRow.extend({
    addAttributes() {
        return {
            ...(this.parent?.() || {}),
            rowHeight: {
                default: null,
                parseHTML: (e) => e.style.height || null,
                renderHTML: (a) =>
                    a.rowHeight ? { style: `height:${a.rowHeight}` } : {},
            },
        };
    },
});
const cellAttributes = (parent) => ({
    ...(parent || {}),
    backgroundColor: {
        default: null,
        parseHTML: (e) => e.style.backgroundColor || null,
        renderHTML: (a) =>
            a.backgroundColor
                ? { style: `background-color:${a.backgroundColor}` }
                : {},
    },
    verticalAlign: {
        default: "top",
        parseHTML: (e) => e.style.verticalAlign || "top",
        renderHTML: (a) => ({ style: `vertical-align:${a.verticalAlign}` }),
    },
});
const EnhancedCell = TableCell.extend({
    addAttributes() {
        return cellAttributes(this.parent?.());
    },
});
const EnhancedHeader = TableHeader.extend({
    addAttributes() {
        return cellAttributes(this.parent?.());
    },
});
const EnhancedImage = Image.extend({
    addAttributes() {
        return {
            ...(this.parent?.() || {}),
            imageAlign: {
                default: "center",
                parseHTML: (e) => e.getAttribute("data-align") || "center",
                renderHTML: (a) => ({ "data-align": a.imageAlign }),
            },
        };
    },
});

const Tool = ({ active, title, onClick, children }) => (
    <button
        type="button"
        title={title}
        onClick={onClick}
        className={`grid h-8 min-w-8 place-items-center rounded px-2 text-xs font-bold ${active ? "bg-sky-100 text-sky-800" : "hover:bg-slate-100 dark:hover:bg-white/10"}`}
    >
        {children}
    </button>
);

export default function DocumentRichEditor({
    value,
    onChange,
    placeholderGroups,
    minHeight = "520px",
}) {
    const [signatureOpen, setSignatureOpen] = useState(false);
    const fileRef = useRef(null);
    const editor = useEditor({
        shouldRerenderOnTransaction: true,
        extensions: [
            StarterKit,
            Underline,
            TextAlign.configure({ types: ["heading", "paragraph"] }),
            EnhancedTable.configure({
                resizable: true,
                handleWidth: 8,
                cellMinWidth: 40,
                lastColumnResizable: true,
                View: DocumentTableView,
            }),
            EnhancedRow,
            EnhancedHeader,
            EnhancedCell,
            TextStyle,
            FontFamily,
            Color,
            Highlight.configure({ multicolor: true }),
            EnhancedImage.configure({
                allowBase64: true,
                resize: {
                    enabled: true,
                    directions: [
                        "topLeft",
                        "topRight",
                        "bottomLeft",
                        "bottomRight",
                    ],
                    minWidth: 40,
                    minHeight: 20,
                    alwaysPreserveAspectRatio: true,
                },
            }),
        ],
        content: value || "<p></p>",
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
        editorProps: { attributes: { class: "document-editor outline-none" } },
    });
    useEffect(() => {
        if (editor && editor.getHTML() !== (value || "<p></p>"))
            editor.commands.setContent(value || "<p></p>", {
                emitUpdate: false,
            });
    }, [editor, value]);
    if (!editor) return null;
    const chain = () => editor.chain().focus();
    const inTable = editor.isActive("table");
    const onImage = editor.isActive("image");
    const addImage = (file) => {
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) {
            alert("Ukuran gambar maksimal 2 MB.");
            return;
        }
        const reader = new FileReader();
        reader.onload = () =>
            chain().setImage({ src: reader.result, alt: file.name }).run();
        reader.readAsDataURL(file);
    };
    const insertTable = () => {
        const rows = Math.max(
            1,
            Math.min(30, Number(prompt("Jumlah baris tabel", "3")) || 3),
        );
        const cols = Math.max(
            1,
            Math.min(20, Number(prompt("Jumlah kolom tabel", "3")) || 3),
        );
        chain()
            .insertTable({
                rows,
                cols,
                withHeaderRow: confirm("Gunakan baris pertama sebagai header?"),
            })
            .run();
    };
    const setTableWidth = (v) =>
        editor
            .chain()
            .focus()
            .updateAttributes("table", { tableWidth: v })
            .run();
    const setTableAlign = (v) =>
        editor
            .chain()
            .focus()
            .updateAttributes("table", { tableAlign: v })
            .run();
    const setAncestorAttrs = (type, attrs) => {
        const { state, dispatch } = editor.view;
        const { $from } = state.selection;
        for (let depth = $from.depth; depth > 0; depth--) {
            const node = $from.node(depth);
            if (node.type.name === type) {
                dispatch(
                    state.tr.setNodeMarkup($from.before(depth), undefined, {
                        ...node.attrs,
                        ...attrs,
                    }),
                );
                editor.view.focus();
                return true;
            }
        }
        return false;
    };
    return (
        <div className="overflow-hidden rounded-lg border bg-white dark:border-white/10 dark:bg-slate-950">
            <div className="flex flex-wrap items-center gap-1 border-b bg-slate-50 p-2 dark:border-white/10 dark:bg-white/5">
                <select
                    className="h-8 rounded border bg-white px-2 text-xs dark:bg-slate-900"
                    value={editor.getAttributes("textStyle").fontFamily || ""}
                    onChange={(e) =>
                        e.target.value
                            ? chain().setFontFamily(e.target.value).run()
                            : chain().unsetFontFamily().run()
                    }
                >
                    <option value="">Font</option>
                    <option value="Arial">Arial</option>
                    <option value="Georgia">Georgia</option>
                    <option value="'Times New Roman'">Times New Roman</option>
                    <option value="'Courier New'">Courier New</option>
                </select>
                <select
                    className="h-8 rounded border bg-white px-2 text-xs dark:bg-slate-900"
                    value={
                        editor.isActive("heading", { level: 1 })
                            ? "h1"
                            : editor.isActive("heading", { level: 2 })
                              ? "h2"
                              : "p"
                    }
                    onChange={(e) =>
                        e.target.value === "p"
                            ? chain().setParagraph().run()
                            : chain()
                                  .toggleHeading({
                                      level: Number(e.target.value.slice(1)),
                                  })
                                  .run()
                    }
                >
                    <option value="p">Paragraf</option>
                    <option value="h1">Judul 1</option>
                    <option value="h2">Judul 2</option>
                </select>
                <Tool
                    title="Bold"
                    active={editor.isActive("bold")}
                    onClick={() => chain().toggleBold().run()}
                >
                    <Bold size={15} />
                </Tool>
                <Tool
                    title="Miring"
                    active={editor.isActive("italic")}
                    onClick={() => chain().toggleItalic().run()}
                >
                    <Italic size={15} />
                </Tool>
                <Tool
                    title="Garis bawah"
                    active={editor.isActive("underline")}
                    onClick={() => chain().toggleUnderline().run()}
                >
                    <UnderlineIcon size={15} />
                </Tool>
                <Tool
                    title="Coret"
                    active={editor.isActive("strike")}
                    onClick={() => chain().toggleStrike().run()}
                >
                    <Strikethrough size={15} />
                </Tool>
                <input
                    type="color"
                    title="Warna teks"
                    className="h-8 w-8 cursor-pointer rounded border p-1"
                    onInput={(e) => chain().setColor(e.target.value).run()}
                />
                <input
                    type="color"
                    title="Warna sorot"
                    className="h-8 w-8 cursor-pointer rounded border p-1"
                    onInput={(e) =>
                        chain().toggleHighlight({ color: e.target.value }).run()
                    }
                />
                {[
                    ["left", AlignLeft],
                    ["center", AlignCenter],
                    ["right", AlignRight],
                    ["justify", AlignJustify],
                ].map(([align, Icon]) => (
                    <Tool
                        key={align}
                        title={`Rata ${align}`}
                        active={editor.isActive({ textAlign: align })}
                        onClick={() => chain().setTextAlign(align).run()}
                    >
                        <Icon size={15} />
                    </Tool>
                ))}
                <Tool
                    title="Daftar"
                    active={editor.isActive("bulletList")}
                    onClick={() => chain().toggleBulletList().run()}
                >
                    <List size={15} />
                </Tool>
                <Tool
                    title="Daftar angka"
                    active={editor.isActive("orderedList")}
                    onClick={() => chain().toggleOrderedList().run()}
                >
                    <ListOrdered size={15} />
                </Tool>
                <Tool title="Buat tabel (ukuran bebas)" onClick={insertTable}>
                    <Table2 size={15} />
                </Tool>
                <input
                    ref={fileRef}
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    className="hidden"
                    onChange={(e) => {
                        addImage(e.target.files?.[0]);
                        e.target.value = "";
                    }}
                />
                <Tool
                    title="Sisipkan gambar"
                    onClick={() => fileRef.current?.click()}
                >
                    <ImagePlus size={15} />
                </Tool>
                <Tool
                    title="Gambar tanda tangan"
                    onClick={() => setSignatureOpen(true)}
                >
                    <PenLine size={15} />
                </Tool>
                <Tool
                    title="Garis"
                    onClick={() => chain().setHorizontalRule().run()}
                >
                    <Pilcrow size={15} />
                </Tool>
                <Tool title="Urungkan" onClick={() => chain().undo().run()}>
                    <Undo2 size={15} />
                </Tool>
                <Tool title="Ulangi" onClick={() => chain().redo().run()}>
                    <Redo2 size={15} />
                </Tool>
                <select
                    className="h-8 max-w-52 rounded border bg-white px-2 text-xs font-bold dark:bg-slate-900"
                    value=""
                    onChange={(e) => {
                        if (e.target.value)
                            chain()
                                .insertContent(`{{${e.target.value}}}`)
                                .run();
                        e.target.value = "";
                    }}
                >
                    <option value="">+ Sisipkan Data</option>
                    {Object.entries(placeholderGroups).map(([group, items]) => (
                        <optgroup key={group} label={group}>
                            {Object.entries(items).map(([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ))}
                        </optgroup>
                    ))}
                </select>
            </div>
            {inTable && (
                <div className="flex flex-wrap items-center gap-1 border-b bg-amber-50 p-2 dark:border-white/10 dark:bg-amber-950/20">
                    <span className="mr-1 text-xs font-extrabold">TABEL:</span>
                    <Tool
                        title="Tambah baris sebelum"
                        onClick={() => chain().addRowBefore().run()}
                    >
                        <Rows3 size={14} /> + Atas
                    </Tool>
                    <Tool
                        title="Tambah baris setelah"
                        onClick={() => chain().addRowAfter().run()}
                    >
                        <Rows3 size={14} /> + Bawah
                    </Tool>
                    <Tool
                        title="Hapus baris"
                        onClick={() => chain().deleteRow().run()}
                    >
                        <Trash2 size={14} /> Baris
                    </Tool>
                    <Tool
                        title="Tambah kolom sebelum"
                        onClick={() => chain().addColumnBefore().run()}
                    >
                        <Columns3 size={14} /> + Kiri
                    </Tool>
                    <Tool
                        title="Tambah kolom setelah"
                        onClick={() => chain().addColumnAfter().run()}
                    >
                        <Columns3 size={14} /> + Kanan
                    </Tool>
                    <Tool
                        title="Hapus kolom"
                        onClick={() => chain().deleteColumn().run()}
                    >
                        <Trash2 size={14} /> Kolom
                    </Tool>
                    <Tool
                        title="Gabungkan cell terpilih"
                        onClick={() => chain().mergeCells().run()}
                    >
                        <Merge size={14} /> Gabung
                    </Tool>
                    <Tool
                        title="Pisahkan cell"
                        onClick={() => chain().splitCell().run()}
                    >
                        <Split size={14} /> Pisah
                    </Tool>
                    <Tool
                        title="Header baris"
                        onClick={() => chain().toggleHeaderRow().run()}
                    >
                        Header Baris
                    </Tool>
                    <Tool
                        title="Header kolom"
                        onClick={() => chain().toggleHeaderColumn().run()}
                    >
                        Header Kolom
                    </Tool>
                    <select
                        className="h-8 rounded border bg-white px-2 text-xs"
                        defaultValue="100%"
                        onChange={(e) =>
                            setAncestorAttrs("table", {
                                tableWidth: e.target.value,
                            })
                        }
                    >
                        <option value="100%">Lebar 100%</option>
                        <option value="75%">Lebar 75%</option>
                        <option value="50%">Lebar 50%</option>
                        <option value="auto">Lebar Otomatis</option>
                    </select>
                    <Tool
                        title="Lebar tabel khusus"
                        onClick={() => {
                            const w = prompt(
                                "Lebar tabel (contoh: 85%, 500px, 160mm)",
                                "85%",
                            );
                            if (w) setAncestorAttrs("table", { tableWidth: w });
                        }}
                    >
                        Lebar Khusus
                    </Tool>
                    <select
                        className="h-8 rounded border bg-white px-2 text-xs"
                        defaultValue="left"
                        onChange={(e) =>
                            setAncestorAttrs("table", {
                                tableAlign: e.target.value,
                            })
                        }
                    >
                        <option value="left">Tabel Kiri</option>
                        <option value="center">Tabel Tengah</option>
                        <option value="right">Tabel Kanan</option>
                    </select>
                    <select
                        className="h-8 rounded border bg-white px-2 text-xs"
                        defaultValue="left"
                        onChange={(e) =>
                            chain()
                                .setCellAttribute("align", e.target.value)
                                .run()
                        }
                    >
                        <option value="left">Teks Cell Kiri</option>
                        <option value="center">Teks Cell Tengah</option>
                        <option value="right">Teks Cell Kanan</option>
                    </select>
                    <select
                        className="h-8 rounded border bg-white px-2 text-xs"
                        defaultValue="top"
                        onChange={(e) =>
                            chain()
                                .setCellAttribute(
                                    "verticalAlign",
                                    e.target.value,
                                )
                                .run()
                        }
                    >
                        <option value="top">Cell Atas</option>
                        <option value="middle">Cell Tengah</option>
                        <option value="bottom">Cell Bawah</option>
                    </select>
                    <input
                        type="color"
                        title="Warna cell"
                        className="h-8 w-8 rounded border p-1"
                        onInput={(e) =>
                            chain()
                                .setCellAttribute(
                                    "backgroundColor",
                                    e.target.value,
                                )
                                .run()
                        }
                    />
                    <Tool
                        title="Atur tinggi baris"
                        onClick={() => {
                            const h = prompt(
                                "Tinggi baris (contoh: 40px atau 15mm)",
                                "40px",
                            );
                            if (h)
                                setAncestorAttrs("tableRow", { rowHeight: h });
                        }}
                    >
                        Tinggi Baris
                    </Tool>
                    <Tool
                        title="Hapus seluruh tabel"
                        onClick={() => chain().deleteTable().run()}
                    >
                        <Minus size={14} /> Hapus Tabel
                    </Tool>
                </div>
            )}
            {onImage && (
                <div className="flex flex-wrap items-center gap-1 border-b bg-sky-50 p-2 dark:border-white/10 dark:bg-sky-950/20">
                    <span className="mr-1 text-xs font-extrabold">GAMBAR:</span>
                    <Tool
                        title="Posisi kiri"
                        onClick={() =>
                            chain()
                                .updateAttributes("image", {
                                    imageAlign: "left",
                                })
                                .run()
                        }
                    >
                        <AlignLeft size={15} />
                    </Tool>
                    <Tool
                        title="Posisi tengah"
                        onClick={() =>
                            chain()
                                .updateAttributes("image", {
                                    imageAlign: "center",
                                })
                                .run()
                        }
                    >
                        <AlignCenter size={15} />
                    </Tool>
                    <Tool
                        title="Posisi kanan"
                        onClick={() =>
                            chain()
                                .updateAttributes("image", {
                                    imageAlign: "right",
                                })
                                .run()
                        }
                    >
                        <AlignRight size={15} />
                    </Tool>
                    {[100, 150, 220, 300, 450].map((w) => (
                        <Tool
                            key={w}
                            title={`Lebar ${w}px`}
                            onClick={() =>
                                chain()
                                    .updateAttributes("image", {
                                        width: w,
                                        height: null,
                                    })
                                    .run()
                            }
                        >
                            {w}px
                        </Tool>
                    ))}
                    <Tool
                        title="Ukuran gambar khusus"
                        onClick={() => {
                            const w = Number(
                                prompt("Lebar gambar dalam pixel", "220"),
                            );
                            if (w > 10)
                                chain()
                                    .updateAttributes("image", {
                                        width: w,
                                        height: null,
                                    })
                                    .run();
                        }}
                    >
                        Ukuran Khusus
                    </Tool>
                    <Tool
                        title="Hapus gambar"
                        onClick={() => chain().deleteSelection().run()}
                    >
                        <Trash2 size={14} /> Hapus
                    </Tool>
                    <span className="ml-2 text-xs text-slate-500">
                        Tarik titik sudut gambar untuk resize bebas.
                    </span>
                </div>
            )}
            <div
                className="document-paper theme-light-surface mx-auto overflow-auto px-12 py-10 text-slate-950"
                style={{ minHeight }}
            >
                <EditorContent editor={editor} />
            </div>
            <style>{`.document-editor{min-height:${minHeight};font-family:Arial,sans-serif;line-height:1.55}.document-editor p{margin:0 0 .7em}.document-editor h1{font-size:1.65em;font-weight:700;margin:.6em 0}.document-editor h2{font-size:1.3em;font-weight:700;margin:.5em 0}.document-editor ul{list-style:disc;padding-left:1.5rem}.document-editor ol{list-style:decimal;padding-left:1.5rem}.document-editor .tableWrapper{overflow-x:auto;padding:6px}.document-editor table{border-collapse:collapse;margin:.75rem 0;table-layout:fixed}.document-editor table[data-align="center"]{margin-left:auto;margin-right:auto}.document-editor table[data-align="right"]{margin-left:auto;margin-right:0}.document-editor td,.document-editor th{border:1px solid #555;padding:.4rem;min-width:2rem;position:relative}.document-editor th{background:#f1f5f9}.document-editor .column-resize-handle{position:absolute;right:-4px;top:0;bottom:-2px;width:8px;background:#0ea5e9;pointer-events:none}.document-editor.resize-cursor{cursor:col-resize}.document-editor mark{border-radius:2px;padding:0 2px}.document-editor img{max-width:100%;height:auto}.document-editor img[data-align="left"]{margin-right:auto}.document-editor img[data-align="center"]{margin-left:auto;margin-right:auto}.document-editor img[data-align="right"]{margin-left:auto}.document-editor [data-resize-wrapper]{display:table!important}.document-editor [data-resize-wrapper]:has(img[data-align="left"]){margin-right:auto!important}.document-editor [data-resize-wrapper]:has(img[data-align="center"]){margin-left:auto!important;margin-right:auto!important}.document-editor [data-resize-wrapper]:has(img[data-align="right"]){margin-left:auto!important;margin-right:0!important}.document-editor .selectedCell:after{content:"";position:absolute;inset:0;background:#0ea5e933;pointer-events:none}`}</style>
            {signatureOpen && (
                <SignatureDialog
                    onClose={() => setSignatureOpen(false)}
                    onSave={(src) => {
                        chain()
                            .setImage({ src, alt: "Tanda tangan", width: 220 })
                            .run();
                        setSignatureOpen(false);
                    }}
                />
            )}
        </div>
    );
}

function SignatureDialog({ onClose, onSave }) {
    const canvasRef = useRef(null);
    const drawing = useRef(false);
    useEffect(() => {
        const c = canvasRef.current;
        const ctx = c.getContext("2d");
        ctx.fillStyle = "white";
        ctx.fillRect(0, 0, c.width, c.height);
        ctx.strokeStyle = "#111";
        ctx.lineWidth = 2.5;
        ctx.lineCap = "round";
    }, []);
    const point = (e) => {
        const c = canvasRef.current,
            r = c.getBoundingClientRect(),
            src = e.touches?.[0] || e;
        return [
            (src.clientX - r.left) * (c.width / r.width),
            (src.clientY - r.top) * (c.height / r.height),
        ];
    };
    const start = (e) => {
        e.preventDefault();
        drawing.current = true;
        const [x, y] = point(e),
            ctx = canvasRef.current.getContext("2d");
        ctx.beginPath();
        ctx.moveTo(x, y);
    };
    const move = (e) => {
        if (!drawing.current) return;
        e.preventDefault();
        const [x, y] = point(e),
            ctx = canvasRef.current.getContext("2d");
        ctx.lineTo(x, y);
        ctx.stroke();
    };
    const stop = () => {
        drawing.current = false;
    };
    const clear = () => {
        const c = canvasRef.current,
            ctx = c.getContext("2d");
        ctx.clearRect(0, 0, c.width, c.height);
        ctx.fillStyle = "white";
        ctx.fillRect(0, 0, c.width, c.height);
    };
    return (
        <div className="fixed inset-0 z-[150] grid place-items-center bg-slate-950/70 p-4">
            <div className="w-full max-w-2xl rounded-xl bg-white p-5 text-slate-900 shadow-2xl">
                <h3 className="text-lg font-extrabold">Gambar Tanda Tangan</h3>
                <p className="mb-3 text-sm text-slate-500">
                    Gunakan mouse, touchpad, atau layar sentuh.
                </p>
                <canvas
                    ref={canvasRef}
                    width="900"
                    height="300"
                    className="h-56 w-full touch-none rounded-lg border-2 border-dashed bg-white"
                    onMouseDown={start}
                    onMouseMove={move}
                    onMouseUp={stop}
                    onMouseLeave={stop}
                    onTouchStart={start}
                    onTouchMove={move}
                    onTouchEnd={stop}
                />
                <div className="mt-4 flex justify-end gap-2">
                    <button
                        type="button"
                        onClick={clear}
                        className="rounded border px-4 py-2 font-bold"
                    >
                        Bersihkan
                    </button>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded border px-4 py-2 font-bold"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        onClick={() =>
                            onSave(canvasRef.current.toDataURL("image/png"))
                        }
                        className="rounded bg-sky-600 px-4 py-2 font-bold text-white"
                    >
                        Sisipkan Tanda Tangan
                    </button>
                </div>
            </div>
        </div>
    );
}
