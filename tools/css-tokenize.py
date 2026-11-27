#!/usr/bin/env python3
"""Ganti warna hex hardcoded di luar blok :root dengan var(--token).

Hanya warna yang SUDAH punya token padanan. Warna lain dibiarkan apa
adanya dan dilaporkan, supaya tidak ada substitusi yang salah arti.
"""
import re
import pathlib
import sys

MAP = {
    '#b42340': 'var(--danger)',
    '#921b34': 'var(--danger-hover)',
    '#fff5f6': 'var(--danger-soft)',
    '#ffc0cc': 'var(--danger-line)',
    '#ffadbd': 'var(--danger-ink)',
    '#fdf0f1': 'var(--danger-soft)',
    '#f1cdd4': 'var(--danger-line)',
    '#267160': 'var(--success)',
    '#f0f9f4': 'var(--success-soft)',
    '#886125': 'var(--warning)',
    '#fff6e3': 'var(--warning-soft)',
    '#f7f9fc': 'var(--surface-subtle)',
    '#edf0f5': 'var(--surface-muted)',
    '#fafbfd': 'var(--surface-raised)',
    '#d9e0e9': 'var(--line-strong)',
    '#eef2f7': 'var(--line-soft)',
    '#eef1f6': 'var(--line-soft)',
    '#f0f3f7': 'var(--line-soft)',
    '#b9c6da': 'var(--line-strong)',
    '#b9c9e1': 'var(--line-strong)',
    '#536176': 'var(--muted-ink)',
    '#2459bf19': 'var(--focus-ring)',
    '#e3e8ef': 'var(--line)',
    '#ccd5e1': 'var(--field-line)',
    '#34445b': 'var(--label)',
    '#647187': 'var(--muted)',
    '#1d2b43': 'var(--ink)',
    '#2459bf': 'var(--blue)',
    '#19479f': 'var(--blue-hover)',
    '#edf3ff': 'var(--blue-soft)',
}

skip_files = {'repository.css'}  # file sumber token; hanya :root yang disentuh


def is_root_block(line: str) -> bool:
    return bool(re.match(r'^\s*:root', line))


def transform(path: pathlib.Path) -> tuple[int, list]:
    src = path.read_text()
    lines = src.splitlines(keepends=True)
    out = []
    changed = 0
    in_root = False
    depth = 0

    for ln in lines:
        if is_root_block(ln):
            in_root = True
            depth = ln.count('{') - ln.count('}')
            out.append(ln)
            continue
        if in_root:
            depth += ln.count('{') - ln.count('}')
            if depth <= 0:
                in_root = False
            out.append(ln)
            continue

        def repl(m):
            nonlocal changed
            hexval = m.group(1).lower()
            if hexval in MAP:
                changed += 1
                return MAP[hexval]
            return m.group(0)

        out.append(re.sub(r'(#[0-9a-fA-F]{3,8})\b', repl, ln))

    if changed:
        path.write_text(''.join(out))
    return changed, []


def residual(path: pathlib.Path) -> list:
    lines = path.read_text().splitlines()
    in_root = False
    depth = 0
    res = []
    for i, ln in enumerate(lines, 1):
        if re.match(r'^\s*:root', ln):
            in_root = True
            depth = ln.count('{') - ln.count('}')
            continue
        if in_root:
            depth += ln.count('{') - ln.count('}')
            if depth <= 0:
                in_root = False
            continue
        for m in re.finditer(r'#[0-9a-fA-F]{3,8}\b', ln):
            res.append((i, m.group(0), ln.strip()[:64]))
    return res


def main():
    cssdir = pathlib.Path('resources/css')
    total = 0
    for p in sorted(cssdir.glob('*.css')):
        # repository.css juga diproses: blok :root dilewati oleh transform(),
        # jadi definisi token tidak tersentuh.
        n, _ = transform(p)
        total += n
        print(f"{p.name}: {n} hex -> token")

    print(f"\ntotal diganti: {total}")
    print("\n--- sisa hex di luar :root ---")
    for p in sorted(cssdir.glob('*.css')):
        res = residual(p)
        if res:
            print(f"{p.name}: {len(res)}")
            for r in res[:8]:
                print(f"   L{r[0]} {r[1]}  {r[2]}")


if __name__ == '__main__':
    main()
