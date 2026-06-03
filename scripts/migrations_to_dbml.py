#!/usr/bin/env python3
"""
Generate dbdiagram.io DBML from Laravel migrations (ordered by filename).
Processes SQL ALTER fragments and Blueprint blocks in source order within each file.
"""
from __future__ import annotations

import re
import sys
from collections import OrderedDict
from pathlib import Path


def migration_files(root: Path) -> list[Path]:
    return sorted(root.rglob("*.php"), key=lambda p: p.name)


def strip_php_comments(text: str) -> str:
    text = re.sub(r"/\*.*?\*/", "", text, flags=re.S)
    text = re.sub(r"//[^\n]*", "", text)
    return text


def extract_brace_block(s: str, open_brace_idx: int) -> tuple[str, int]:
    depth = 0
    i = open_brace_idx
    start_content = i + 1
    while i < len(s):
        c = s[i]
        if c == "{":
            depth += 1
        elif c == "}":
            depth -= 1
            if depth == 0:
                return s[start_content:i], i + 1
        i += 1
    return s[start_content:], len(s)


def split_table_calls(body: str) -> list[str]:
    body = strip_php_comments(body)
    calls: list[str] = []
    i = 0
    n = len(body)
    while True:
        j = body.find("$table->", i)
        if j < 0:
            break
        depth = 0
        in_squote = False
        in_dquote = False
        k = j
        while k < n:
            ch = body[k]
            if not in_squote and not in_dquote:
                if ch == "'":
                    in_squote = True
                elif ch == '"':
                    in_dquote = True
                elif ch == "(":
                    depth += 1
                elif ch == ")":
                    depth -= 1
                elif ch == ";" and depth == 0:
                    calls.append(body[j : k + 1].strip())
                    k += 1
                    break
            else:
                if in_squote and ch == "\\":
                    k += 1
                elif in_squote and ch == "'":
                    in_squote = False
                elif in_dquote and ch == "\\":
                    k += 1
                elif in_dquote and ch == '"':
                    in_dquote = False
            k += 1
        i = k
    return calls


def first_string_arg(args: str) -> str | None:
    m = re.match(r"\s*'([^']*)'", args)
    return m.group(1) if m else None


def second_string_or_int(args: str) -> str | int | None:
    m = re.match(r"\s*'([^']*)'\s*,\s*(\d+)", args)
    if m:
        return int(m.group(2))
    m2 = re.match(r"\s*'([^']*)'\s*,\s*'([^']*)'", args)
    if m2:
        return m2.group(2)
    return None


def parse_decimal_args(call: str) -> tuple[int, int] | None:
    m = re.search(r"->decimal\(\s*'[^']+'\s*,\s*(\d+)\s*,\s*(\d+)", call)
    if m:
        return int(m.group(1)), int(m.group(2))
    return None


def laravel_default_to_dbml(stmt: str) -> str | None:
    m = re.search(r"->default\(\s*(true|false)\s*\)", stmt)
    if m:
        return m.group(1)
    m = re.search(r"->default\(\s*(-?\d+)\s*\)", stmt)
    if m:
        return m.group(1)
    m = re.search(r"->default\(\s*'([^']*)'\s*\)", stmt)
    if m:
        return f"'{m.group(1)}'"
    return None


def column_note_from_stmt(stmt: str) -> str | None:
    notes: list[str] = []
    m = re.search(r"DB::raw\(\s*'([^']+)'", stmt)
    if m:
        notes.append(f"default raw: {m.group(1)}")
    m = re.search(r"->comment\(\s*'([^']*)'\s*\)", stmt)
    if m:
        notes.append(m.group(1))
    if notes:
        return "; ".join(notes)
    return None


def blueprint_call_to_column(call: str) -> list[tuple[str, str, dict]]:
    m = re.match(r"\$table->(\w+)\((.*)$", call, re.S)
    if not m:
        return []
    method, rest = m.group(1), m.group(2)
    extra: dict = {
        "pk": False,
        "nullable": False,
        "unique": False,
        "default": None,
        "note": None,
    }

    def apply_modifiers(stmt: str) -> None:
        if "->nullable(" in stmt or "->nullable()" in stmt:
            extra["nullable"] = True
        if "->primary(" in stmt or "->primary()" in stmt:
            extra["pk"] = True
        if re.search(r"->unique\(\s*\)", stmt) and "->unique([" not in stmt:
            extra["unique"] = True
        d = laravel_default_to_dbml(stmt)
        if d is not None:
            extra["default"] = d
        n = column_note_from_stmt(stmt)
        if n:
            extra["note"] = n

    if method in {"foreign", "dropForeign", "dropUnique", "dropIndex", "index"}:
        return []

    if method == "timestamps":
        apply_modifiers(call)
        rows = []
        for name, typ in [("created_at", "timestamp"), ("updated_at", "timestamp")]:
            e = dict(extra)
            e["nullable"] = False
            rows.append((name, typ, e))
        return rows

    if method == "softDeletes":
        apply_modifiers(call)
        e = dict(extra)
        e["nullable"] = True
        return [("deleted_at", "timestamp", e)]

    if method == "rememberToken":
        return [("remember_token", "varchar(100)", {**extra, "nullable": True})]

    if method == "renameColumn":
        m_old = re.search(r"renameColumn\(\s*'([^']+)'\s*,\s*'([^']+)'", call)
        if m_old:
            return [("__rename__", f"{m_old.group(1)}->{m_old.group(2)}", {})]
        return []

    if method == "dropColumn":
        mcols = re.search(r"dropColumn\(\s*\[(.*?)\]\s*\)", call, re.S)
        if mcols:
            cols = re.findall(r"'([^']+)'", mcols.group(1))
            return [("__drop__", ",".join(cols), {})]
        mone = re.search(r"dropColumn\(\s*'([^']+)'", call)
        if mone:
            return [("__drop__", mone.group(1), {})]
        return []

    args_match = re.match(r"(.*?)\)(?:\s*->|\s*;)", rest)
    if not args_match:
        return []
    args_inner = args_match.group(1)
    col = first_string_arg(args_inner)

    apply_modifiers(call)

    if method == "uuid":
        return [(col, "uuid", extra)]
    if method == "string":
        if col is None:
            return []
        ln = second_string_or_int(args_inner)
        if isinstance(ln, int):
            return [(col, f"varchar({ln})", extra)]
        return [(col, "varchar(255)", extra)]
    if method == "text":
        if col is None:
            return []
        return [(col, "text", extra)]
    if method == "boolean":
        return [(col, "boolean", extra)]
    if method == "integer":
        return [(col, "int", extra)]
    if method == "bigInteger":
        return [(col, "bigint", extra)]
    if method == "decimal":
        if col is None:
            return []
        p_s = parse_decimal_args(call)
        if p_s:
            p, s = p_s
            return [(col, f"decimal({p},{s})", extra)]
        return [(col, "decimal(18,4)", extra)]
    if method == "date":
        return [(col, "date", extra)]
    if method == "timestamp":
        return [(col, "timestamp", extra)]
    if method == "json":
        return [(col, "json", extra)]
    if method == "jsonb":
        return [(col, "jsonb", extra)]

    return []


def parse_foreign_call(call: str) -> dict | None:
    m = re.search(
        r"foreign\(\s*'([^']+)'(?:\s*,\s*'([^']+)')?\s*\)"
        r".*?references\(\s*'([^']+)'\s*\)"
        r".*?->on\(\s*'([^']+)'\s*\)",
        call,
        re.S,
    )
    if not m:
        return None
    return {
        "from_col": m.group(1),
        "constraint": m.group(2),
        "to_col": m.group(3),
        "to_table": m.group(4),
    }


def fk_on_delete(call: str) -> str | None:
    m = re.search(r"->onDelete\(\s*'([^']+)'", call)
    if m:
        return m.group(1)
    if "nullOnDelete()" in call:
        return "set null"
    if "cascadeOnDelete()" in call:
        return "cascade"
    return None


def parse_drop_foreign_arg(call: str) -> str | None:
    m = re.search(r"dropForeign\(\s*\[\s*'([^']+)'\s*\]\s*\)", call)
    if m:
        return m.group(1)
    m2 = re.search(r"dropForeign\(\s*'([^']+)'\s*\)", call)
    if m2:
        return m2.group(1)
    return None


def table_base_name(full: str) -> str:
    return full.split(".")[-1] if "." in full else full


def fk_constraint_to_column(constraint: str, full_table: str) -> str | None:
    base = table_base_name(full_table)
    prefix = base + "_"
    if constraint.startswith(prefix) and constraint.endswith("_foreign"):
        mid = constraint[len(prefix) : -len("_foreign")]
        return mid or None
    return None


def relocate_table(tables: dict, fks: list, old: str, new: str) -> None:
    if old in tables:
        tables[new] = tables.pop(old)
    for fk in fks:
        if fk["from_table"] == old:
            fk["from_table"] = new
        if fk["to_table"] == old:
            fk["to_table"] = new


def append_fk(fks: list, from_table: str, d: dict, on_delete: str | None) -> None:
    fks.append(
        {
            "from_table": from_table,
            "from_col": d["from_col"],
            "to_table": d["to_table"],
            "to_col": d["to_col"],
            "on_delete": on_delete,
            "constraint": d.get("constraint"),
        }
    )


def apply_drop_fk_sql(fks: list, tbl: str, cname: str) -> None:
    col = fk_constraint_to_column(cname, tbl)
    if col:
        fks[:] = [fk for fk in fks if not (fk["from_table"] == tbl and fk["from_col"] == col)]
    else:
        fks[:] = [fk for fk in fks if not (fk["from_table"] == tbl and fk.get("constraint") == cname)]


def apply_blueprint_block(
    kind: str,
    table: str,
    body: str,
    tables: dict[str, OrderedDict],
    fks: list[dict],
) -> None:
    if kind == "create":
        tables[table] = OrderedDict()
        cols = tables[table]
        for call in split_table_calls(body):
            if call.lstrip().startswith("$table->foreign"):
                d = parse_foreign_call(call)
                if d:
                    append_fk(fks, table, d, fk_on_delete(call))
                continue
            for name, typ, extra in blueprint_call_to_column(call):
                if name.startswith("__"):
                    continue
                cols[name] = (typ, extra)
        return

    if table not in tables:
        tables[table] = OrderedDict()
    cols = tables[table]
    for call in split_table_calls(body):
        if call.lstrip().startswith("$table->foreign"):
            d = parse_foreign_call(call)
            if d:
                append_fk(fks, table, d, fk_on_delete(call))
            continue
        if call.lstrip().startswith("$table->dropForeign"):
            arg = parse_drop_foreign_arg(call)
            if arg:
                fks[:] = [
                    fk
                    for fk in fks
                    if not (
                        fk["from_table"] == table
                        and (fk["from_col"] == arg or fk.get("constraint") == arg)
                    )
                ]
            continue
        for name, typ, extra in blueprint_call_to_column(call):
            if name == "__rename__":
                old, new = typ.split("->", 1)
                if old in cols:
                    cols[new] = cols.pop(old)
                for fk in fks:
                    if fk["from_table"] == table and fk["from_col"] == old:
                        fk["from_col"] = new
                continue
            if name == "__drop__":
                for c in typ.split(","):
                    cols.pop(c, None)
                    fks[:] = [
                        fk for fk in fks if not (fk["from_table"] == table and fk["from_col"] == c)
                    ]
                continue
            if name.startswith("__"):
                continue
            if "->change()" in call and name in cols:
                _old_typ, old_ex = cols[name]
                old_ex = dict(old_ex)
                old_ex.update(extra)
                cols[name] = (typ, old_ex)
            else:
                cols[name] = (typ, extra)


def migration_up_body_only(text: str) -> str:
    """Ignore down() so Schema::table drops are not applied to the model."""
    text_nc = strip_php_comments(text)
    m = re.search(r"function\s+up\s*\([^)]*\)\s*:\s*void\s*\{", text_nc)
    if not m:
        return text_nc
    start = m.end() - 1
    body, _ = extract_brace_block(text_nc, start)
    return body


def migration_events(text: str) -> list[tuple[int, str, object]]:
    text_nc = strip_php_comments(migration_up_body_only(text))
    ev: list[tuple[int, str, object]] = []

    for m in re.finditer(
        r"ALTER\s+TABLE\s+([\w.]+)\s+DROP\s+CONSTRAINT\s+IF\s+EXISTS\s+(\w+)",
        text_nc,
        re.I,
    ):
        ev.append((m.start(), "drop_fk_sql", (m.group(1), m.group(2))))

    for m in re.finditer(
        r"ALTER\s+TABLE\s+([\w.]+)\s+SET\s+SCHEMA\s+(\w+)",
        text_nc,
        re.I,
    ):
        ev.append((m.start(), "move_schema", (m.group(1), m.group(2))))

    for m in re.finditer(
        r"ALTER\s+TABLE\s+([\w.]+)\s+ADD\s+CONSTRAINT\s+\w+\s+FOREIGN\s+KEY\s+\((\w+)\)\s+"
        r"REFERENCES\s+([\w.]+)\((\w+)\)(?:\s+ON\s+DELETE\s+(\w+(?:\s+\w+)?))?",
        text_nc,
        re.I,
    ):
        ev.append((m.start(), "add_fk_sql", m.groups()))

    for m in re.finditer(
        r"Schema::(?:connection\(\s*'[^']+'\s*\)\s*->)?(create|table)\(\s*'([^']+)'",
        text_nc,
    ):
        kind = m.group(1)
        tbl = m.group(2)
        fn_pos = text_nc.find("function", m.end())
        if fn_pos < 0:
            continue
        brace_pos = text_nc.find("{", fn_pos)
        if brace_pos < 0:
            continue
        body, _ = extract_brace_block(text_nc, brace_pos)
        ev.append((m.start(), "blueprint", (kind, tbl, body)))

    ev.sort(key=lambda x: x[0])
    return ev


def apply_migration_file(path: Path, tables: dict[str, OrderedDict], fks: list[dict]) -> None:
    text = path.read_text()
    for _pos, etype, payload in migration_events(text):
        if etype == "drop_fk_sql":
            tbl, cname = payload  # type: ignore
            apply_drop_fk_sql(fks, tbl, cname)
        elif etype == "move_schema":
            old, new_schema = payload  # type: ignore
            _schema, name = old.split(".", 1)
            new_full = f"{new_schema}.{name}"
            relocate_table(tables, fks, old, new_full)
        elif etype == "add_fk_sql":
            from_tbl, from_col, to_tbl, to_col, ondel = payload  # type: ignore
            ondel_norm = None
            if ondel:
                t = ondel.lower()
                if t == "set null":
                    ondel_norm = "set null"
                elif t == "cascade":
                    ondel_norm = "cascade"
                elif t == "restrict":
                    ondel_norm = "restrict"
                else:
                    ondel_norm = t.replace(" ", "_")
            fks.append(
                {
                    "from_table": from_tbl,
                    "from_col": from_col,
                    "to_table": to_tbl,
                    "to_col": to_col,
                    "on_delete": ondel_norm,
                    "constraint": None,
                }
            )
        elif etype == "blueprint":
            kind, tbl, body = payload  # type: ignore
            apply_blueprint_block(kind, tbl, body, tables, fks)


def col_to_dbml(name: str, typ: str, extra: dict) -> str:
    attrs: list[str] = []
    if extra.get("pk"):
        attrs.append("pk")
    if extra.get("unique"):
        attrs.append("unique")
    if not extra.get("nullable") and not extra.get("pk"):
        attrs.append("not null")
    if extra.get("default") is not None:
        attrs.append(f"default: {extra['default']}")
    if extra.get("note"):
        esc = extra["note"].replace("'", "''")
        attrs.append(f"note: '{esc}'")
    if attrs:
        return f"  {name} {typ} [{', '.join(attrs)}]"
    return f"  {name} {typ}"


def quote_table(t: str) -> str:
    return '"' + t.replace('"', '\\"') + '"'


def emit_dbml(tables: dict[str, OrderedDict], fks: list[dict]) -> str:
    lines: list[str] = []
    lines.append("// Generated from database/migrations — import at https://dbdiagram.io")
    lines.append("// Table names include PostgreSQL schema (quoted). Re-run: python3 scripts/migrations_to_dbml.py")
    lines.append("")
    for tbl in sorted(tables.keys()):
        lines.append(f"Table {quote_table(tbl)} {{")
        for cname, (typ, ex) in tables[tbl].items():
            lines.append(col_to_dbml(cname, typ, ex))
        lines.append("}")
        lines.append("")

    seen_ref: set[tuple[str, str, str, str]] = set()
    for fk in fks:
        key = (fk["from_table"], fk["from_col"], fk["to_table"], fk["to_col"])
        if key in seen_ref:
            continue
        if fk["from_table"] not in tables or fk["to_table"] not in tables:
            continue
        if fk["from_col"] not in tables[fk["from_table"]]:
            continue
        if fk["to_col"] not in tables[fk["to_table"]]:
            continue
        seen_ref.add(key)
        del_note = ""
        if fk.get("on_delete"):
            del_note = f" [delete: {fk['on_delete']}]"
        lines.append(
            f"Ref: {quote_table(fk['from_table'])}.{fk['from_col']} > "
            f"{quote_table(fk['to_table'])}.{fk['to_col']}{del_note}"
        )

    return "\n".join(lines) + "\n"


def main() -> int:
    root = Path(__file__).resolve().parents[1]
    mig = root / "database" / "migrations"
    if not mig.is_dir():
        print("database/migrations not found", file=sys.stderr)
        return 1
    tables: dict[str, OrderedDict] = {}
    fks: list[dict] = []
    for f in migration_files(mig):
        if "create_uuid_v7_function" in f.name:
            continue
        apply_migration_file(f, tables, fks)
    out = root / "docs" / "dbdiagram.dbml"
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(emit_dbml(tables, fks))
    print(f"Wrote {out} ({len(tables)} tables)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
