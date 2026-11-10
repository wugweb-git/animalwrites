#!/usr/bin/env python3
"""aw*.css → aw*.min.css (aw.css and the component files aw-*.css in plugins/animal-writes-core/theme/assets/css), and
the Kits kit.css, kit-primitives.css, kit-tokens.css → .min.css: comments and whitespace out, nothing else.

The site serves the .min.css files; the sources stay the readable files in git (the editors load the sources).
Edit a source (aw.css also in its child theme copy; kit-tokens.css comes from tools/build-tokens.py), then run this and
commit both files. The Deploy check runs it with --check and fails when a .min.css is not what this writes from its
source.

Safe by design: strings, url(...) and escapes are copied as they are; a comment is dropped; a run of whitespace becomes
one space, or nothing next to { } ; , > ~ (and after : or (, before ) or !). Spaces around + - * / stay (calc() needs
them), as does the space before ( (media queries: "and (" is not "and("). No rule is moved, merged or rewritten, and
no ; is dropped. A comment that sits between two word characters with no space (a/**/b) stops it: dropping it would
join two tokens, so the source needs a space there.

    python3 tools/ci/css_min.py           # write every .min.css
    python3 tools/ci/css_min.py --check   # exit 1 when a .min.css is missing or out of date
    python3 tools/ci/css_min.py IN OUT    # any other file
"""
import pathlib
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
AW_CSS = ROOT / "plugins/animal-writes-core/theme/assets/css"
KITS_CSS = ROOT / "themes/kits-by-wugweb/assets/css"


def pairs():
    """Each source with its .min.css: every aw*.css (not *.min.css) in AW_CSS, and the three Kits files."""
    aw = [f for f in sorted(AW_CSS.glob("aw*.css")) if not f.name.endswith(".min.css")]
    kits = [KITS_CSS / (n + ".css") for n in ("kit-tokens", "kit", "kit-primitives")]
    return [(f, f.with_name(f.name[: -len(".css")] + ".min.css")) for f in aw + kits]


NO_SPACE_AROUND = set("{};,>~")
NO_SPACE_AFTER = NO_SPACE_AROUND | set(":(")
NO_SPACE_BEFORE = NO_SPACE_AROUND | set(")!")
WS = " \t\n\r\f"


def _word(c):
    return c.isalnum() or c in "-_\\%.#" or ord(c) > 127


def minify(src):
    out = []  # pieces; out[-1][-1] is the last character written
    pending = False  # whitespace seen since the last piece
    i, n = 0, len(src)

    def last():
        return out[-1][-1] if out else ""

    def emit(piece):
        nonlocal pending
        if pending and out and last() not in NO_SPACE_AFTER and piece[0] not in NO_SPACE_BEFORE:
            out.append(" ")
        pending = False
        out.append(piece)

    while i < n:
        c = src[i]
        if c in WS:
            pending = True
            i += 1
        elif src.startswith("/*", i):
            j = src.find("*/", i + 2)
            if j < 0:
                raise ValueError("unclosed comment at offset %d" % i)
            after = src[j + 2] if j + 2 < n else ""
            if not pending and out and after and _word(last()) and _word(after):
                raise ValueError("comment between two word characters at offset %d: put a space around it" % i)
            i = j + 2
        elif c in "\"'":
            j = i + 1
            while j < n and src[j] != c:
                if src[j] == "\\":
                    j += 1
                elif src[j] == "\n":
                    raise ValueError("unclosed string at offset %d" % i)
                j += 1
            if j >= n:
                raise ValueError("unclosed string at offset %d" % i)
            emit(src[i:j + 1])
            i = j + 1
        elif c == "\\":
            emit(src[i:i + 2])  # an escape and the character it escapes, whitespace included
            i += 2
        elif src[i:i + 4].lower() == "url(" and (not out or not _word(last()) or pending):
            j = i + 4
            while j < n and src[j] in WS:
                j += 1
            if j < n and src[j] in "\"'":  # url("..."): the string is copied by the string branch
                emit(src[i:i + 4])
                i += 4
                continue
            k = src.find(")", j)
            if k < 0:
                raise ValueError("unclosed url( at offset %d" % i)
            emit("url(" + src[j:k].rstrip(WS) + ")")
            i = k + 1
        else:
            j = i + 1
            while j < n and src[j] not in WS and src[j] not in "\"'\\" and not src.startswith("/*", j) and src[j] not in NO_SPACE_BEFORE | NO_SPACE_AFTER and src[j - 1] not in NO_SPACE_BEFORE | NO_SPACE_AFTER and src[j:j + 4].lower() != "url(":
                j += 1
            emit(src[i:j])
            i = j
    return "".join(out) + "\n"


def main(argv):
    check = "--check" in argv
    args = [a for a in argv if a != "--check"]
    todo = [(pathlib.Path(args[0]), pathlib.Path(args[1]))] if len(args) == 2 else pairs()
    bad = 0
    for src, dst in todo:
        want = minify(src.read_text(encoding="utf-8"))
        if check:
            if not dst.is_file() or dst.read_text(encoding="utf-8") != want:
                print("OUT OF DATE %s (run: python3 tools/ci/css_min.py, commit it with %s)" % (dst.relative_to(ROOT) if dst.is_relative_to(ROOT) else dst, src.name))
                bad = 1
            else:
                print("css min check passed: %s is %s minified (%d → %d bytes)" % (dst.name, src.name, src.stat().st_size, len(want.encode())))
        else:
            if dst.is_file() and dst.read_text(encoding="utf-8") == want:
                print("up to date %s (%d → %d bytes)" % (dst, src.stat().st_size, len(want.encode())))
                continue  # unchanged: keep its mtime, so the version string and the joined stylesheet stay the same
            dst.write_text(want, encoding="utf-8")
            print("wrote %s (%d → %d bytes)" % (dst, src.stat().st_size, len(want.encode())))
    return bad


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
