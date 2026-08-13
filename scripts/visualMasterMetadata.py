#!/usr/bin/env python3
"""Read/write visual master metadata: IPTC Core via XMP (stills), Matroska tags (video).

EXIF is treated as camera-origin: we read DateTimeOriginal for captured_at and do not
write editorial title/description/keywords into EXIF.
"""
import io
import json
import os
import re
import shutil
import struct
import subprocess
import sys
import tempfile
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple
from xml.sax.saxutils import escape


if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8', errors='replace', line_buffering=True)
else:
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace', line_buffering=True)


SCRIPT_DIR = Path(__file__).parent
ROOT_DIR = SCRIPT_DIR.parent
ASSET_REGISTRY_FILE = ROOT_DIR / 'data' / 'assets' / 'registry.json'
VISUAL_MASTER_DIR = ROOT_DIR / 'media' / 'visual' / 'master'

STILL_EXTS = {'.jpg', '.jpeg', '.png', '.webp'}
VIDEO_EXTS = {'.mkv', '.mp4', '.mov', '.webm', '.m4v'}

XMP_NS = 'http://ns.adobe.com/xap/1.0/\x00'


def respond(payload: Dict[str, Any], exit_code: int = 0) -> None:
    print(json.dumps(payload, ensure_ascii=False))
    sys.exit(exit_code)


def read_payload() -> Dict[str, Any]:
    raw = sys.stdin.read()
    if not raw.strip():
        respond({'ok': False, 'error': 'Empty request payload'}, 1)
    try:
        payload = json.loads(raw)
    except json.JSONDecodeError as exc:
        respond({'ok': False, 'error': f'Invalid JSON: {exc}'}, 1)
    if not isinstance(payload, dict):
        respond({'ok': False, 'error': 'Expected a JSON object payload'}, 1)
    return payload


def normalize_keywords(raw: Any) -> List[str]:
    if isinstance(raw, str):
        parts = re.split(r'[,;]+', raw)
    elif isinstance(raw, list):
        parts = raw
    else:
        parts = []
    out: List[str] = []
    seen = set()
    for part in parts:
        keyword = str(part or '').strip()
        if not keyword:
            continue
        key = keyword.lower()
        if key in seen:
            continue
        seen.add(key)
        out.append(keyword)
    return out


def normalize_display(raw: Any) -> Dict[str, Any]:
    data = raw if isinstance(raw, dict) else {}
    return {
        'title': str(data.get('title') or '').strip(),
        'description': str(data.get('description') or '').strip(),
        'captured_at': str(data.get('captured_at') or '').strip(),
        'keywords': normalize_keywords(data.get('keywords')),
        'synced_at': str(data.get('synced_at') or '').strip(),
    }


def load_registry() -> Dict[str, Any]:
    if not ASSET_REGISTRY_FILE.exists():
        return {'assets': {}}
    try:
        with open(str(ASSET_REGISTRY_FILE), 'r', encoding='utf-8') as handle:
            payload = json.load(handle)
    except Exception:
        return {'assets': {}}
    return payload if isinstance(payload, dict) else {'assets': {}}


def load_asset(asset_id: str) -> Optional[Dict[str, Any]]:
    assets = load_registry().get('assets')
    if not isinstance(assets, dict):
        return None
    asset = assets.get(asset_id)
    return asset if isinstance(asset, dict) else None


def master_path_for_asset(asset: Dict[str, Any]) -> Path:
    asset_id = str(asset.get('id') or '').strip()
    fmt = str(asset.get('master_format') or '').strip().lower()
    master_name = os.path.basename(str(asset.get('master_filename') or '').strip())
    if master_name:
        candidate = VISUAL_MASTER_DIR / master_name
        if candidate.is_file():
            return candidate
    if asset_id and fmt:
        candidate = VISUAL_MASTER_DIR / f'{asset_id}.{fmt}'
        if candidate.is_file():
            return candidate
    return VISUAL_MASTER_DIR / (master_name or f'{asset_id}.{fmt or "bin"}')


def resolve_ffmpeg() -> str:
    bundled = SCRIPT_DIR / 'bin' / ('ffmpeg.exe' if os.name == 'nt' else 'ffmpeg')
    if bundled.is_file():
        return str(bundled)
    env = str(os.environ.get('FFMPEG_PATH') or '').strip()
    if env and Path(env).is_file():
        return env
    found = shutil.which('ffmpeg')
    return found or ''


def resolve_ffprobe() -> str:
    bundled = SCRIPT_DIR / 'bin' / ('ffprobe.exe' if os.name == 'nt' else 'ffprobe')
    if bundled.is_file():
        return str(bundled)
    env = str(os.environ.get('FFPROBE_PATH') or '').strip()
    if env and Path(env).is_file():
        return env
    found = shutil.which('ffprobe')
    if found:
        return found
    ffmpeg = resolve_ffmpeg()
    if ffmpeg:
        sibling = Path(ffmpeg).with_name('ffprobe.exe' if os.name == 'nt' else 'ffprobe')
        if sibling.is_file():
            return str(sibling)
    return ''


def build_xmp_packet(display: Dict[str, Any]) -> bytes:
    title = escape(display.get('title') or '')
    description = escape(display.get('description') or '')
    keywords = display.get('keywords') or []
    subject_items = ''.join(f'<rdf:li>{escape(str(k))}</rdf:li>' for k in keywords)
    captured = escape(display.get('captured_at') or '')
    date_block = f'<photoshop:DateCreated>{captured}</photoshop:DateCreated>' if captured else ''
    xml = (
        '<?xpacket begin="\ufeff" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        '<rdf:Description rdf:about=""'
        ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        ' xmlns:photoshop="http://ns.adobe.com/photoshop/1.0/"'
        ' xmlns:Iptc4xmpCore="http://iptc.org/std/Iptc4xmpCore/1.0/xmlns/">'
        f'<dc:title><rdf:Alt><rdf:li xml:lang="x-default">{title}</rdf:li></rdf:Alt></dc:title>'
        f'<dc:description><rdf:Alt><rdf:li xml:lang="x-default">{description}</rdf:li></rdf:Alt></dc:description>'
        f'<dc:subject><rdf:Bag>{subject_items}</rdf:Bag></dc:subject>'
        f'{date_block}'
        '</rdf:Description></rdf:RDF></x:xmpmeta>'
        '<?xpacket end="w"?>'
    )
    return xml.encode('utf-8')


def _jpeg_split_segments(data: bytes) -> List[Tuple[int, bytes]]:
    if len(data) < 4 or data[0:2] != b'\xff\xd8':
        raise ValueError('Not a JPEG')
    segments: List[Tuple[int, bytes]] = []
    i = 2
    while i < len(data):
        if data[i] != 0xFF:
            segments.append((-1, data[i:]))
            break
        while i < len(data) and data[i] == 0xFF:
            i += 1
        if i >= len(data):
            break
        marker = data[i]
        i += 1
        if marker in (0xD9, 0xDA):  # EOI / SOS
            segments.append((marker, data[i - 2:]))
            break
        if marker >= 0xD0 and marker <= 0xD7:
            continue
        if i + 2 > len(data):
            break
        length = struct.unpack('>H', data[i:i + 2])[0]
        end = i + length
        segments.append((marker, data[i - 2:end]))
        i = end
    return segments


def _is_xmp_app1(payload: bytes) -> bool:
    return payload.startswith(b'\xff\xe1') and b'http://ns.adobe.com/xap/1.0/' in payload[:64]


def write_jpeg_xmp(path: Path, display: Dict[str, Any]) -> None:
    data = path.read_bytes()
    segments = _jpeg_split_segments(data)
    xmp_body = XMP_NS.encode('latin-1') + build_xmp_packet(display)
    app1 = b'\xff\xe1' + struct.pack('>H', len(xmp_body) + 2) + xmp_body
    out = bytearray(b'\xff\xd8')
    inserted = False
    for marker, chunk in segments:
        if marker == 0xE1 and _is_xmp_app1(chunk):
            if not inserted:
                out.extend(app1)
                inserted = True
            continue
        if marker == -1 or marker == 0xDA:
            if not inserted:
                out.extend(app1)
                inserted = True
            out.extend(chunk)
            break
        out.extend(chunk)
    else:
        if not inserted:
            out.extend(app1)
    path.write_bytes(bytes(out))


def write_png_xmp(path: Path, display: Dict[str, Any]) -> None:
    from PIL import Image
    from PIL.PngImagePlugin import PngInfo

    packet = build_xmp_packet(display).decode('utf-8')
    with Image.open(path) as img:
        info = PngInfo()
        # Preserve existing textual chunks except prior XMP.
        for key, value in (img.info or {}).items():
            if str(key).lower() in {'xml:com.adobe.xmp', 'xmp'}:
                continue
            if isinstance(value, str):
                try:
                    info.add_text(str(key), value)
                except Exception:
                    pass
        info.add_text('XML:com.adobe.xmp', packet)
        tmp = path.with_suffix(path.suffix + '.tmp')
        img.save(tmp, format='PNG', pnginfo=info)
    os.replace(tmp, path)


def write_webp_xmp(path: Path, display: Dict[str, Any]) -> None:
    # Pillow WebP save does not reliably round-trip XMP; rewrite via ffmpeg metadata when possible.
    ffmpeg = resolve_ffmpeg()
    if not ffmpeg:
        raise RuntimeError('ffmpeg is required to write WebP XMP metadata')
    title = display.get('title') or ''
    description = display.get('description') or ''
    keywords = ', '.join(display.get('keywords') or [])
    tmp = path.with_suffix(path.suffix + '.tmp.webp')
    cmd = [
        ffmpeg, '-y', '-hide_banner', '-loglevel', 'error',
        '-i', str(path),
        '-map_metadata', '0',
        '-metadata', f'title={title}',
        '-metadata', f'description={description}',
        '-metadata', f'comment={keywords}',
        '-c', 'copy',
        str(tmp),
    ]
    proc = subprocess.run(
        cmd,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        universal_newlines=True,
        encoding='utf-8',
        errors='replace',
    )
    if proc.returncode != 0 or not tmp.is_file():
        if tmp.exists():
            try:
                tmp.unlink()
            except OSError:
                pass
        detail = (proc.stderr or proc.stdout or '').strip()
        raise RuntimeError(detail or 'ffmpeg WebP metadata write failed')
    os.replace(tmp, path)


def read_exif_captured_at(path: Path) -> str:
    """Read camera DateTimeOriginal / DateTime via Pillow (no piexif dependency)."""
    try:
        from PIL import Image
    except ImportError:
        return ''
    try:
        with Image.open(path) as img:
            exif = img.getexif()
            if not exif:
                return ''
            # 36867 = DateTimeOriginal, 306 = DateTime
            raw = exif.get(36867) or exif.get(306)
            if raw is None and hasattr(exif, 'get_ifd'):
                try:
                    exif_ifd = exif.get_ifd(0x8769)
                    raw = exif_ifd.get(36867) or exif_ifd.get(306)
                except Exception:
                    raw = None
    except Exception:
        return ''
    if not raw:
        return ''
    if isinstance(raw, bytes):
        raw = raw.decode('utf-8', errors='ignore')
    text = str(raw).strip()
    # EXIF: "YYYY:MM:DD HH:MM:SS" → YYYY-MM-DD
    m = re.match(r'^(\d{4}):(\d{2}):(\d{2})', text)
    if m:
        return f'{m.group(1)}-{m.group(2)}-{m.group(3)}'
    m = re.match(r'^(\d{4})-(\d{2})-(\d{2})', text)
    if m:
        return m.group(0)
    return ''


def extract_xmp_from_bytes(data: bytes) -> str:
    marker = b'http://ns.adobe.com/xap/1.0/'
    idx = data.find(marker)
    if idx < 0:
        marker2 = b'<x:xmpmeta'
        idx = data.find(marker2)
        if idx < 0:
            return ''
        start = idx
    else:
        start = data.find(b'<', idx)
        if start < 0:
            return ''
    end = data.find(b'</x:xmpmeta>', start)
    if end < 0:
        return ''
    end += len(b'</x:xmpmeta>')
    try:
        return data[start:end].decode('utf-8', errors='ignore')
    except Exception:
        return ''


def parse_xmp_display(xmp: str) -> Dict[str, Any]:
    if not xmp:
        return normalize_display({})

    def first_li(block_name: str) -> str:
        pattern = rf'<{block_name}>.*?<rdf:li[^>]*>(.*?)</rdf:li>'
        m = re.search(pattern, xmp, re.I | re.S)
        if not m:
            return ''
        return re.sub(r'<[^>]+>', '', m.group(1)).strip()

    title = first_li('dc:title')
    description = first_li('dc:description')
    keywords = re.findall(r'<dc:subject>.*?</dc:subject>', xmp, re.I | re.S)
    kw_list: List[str] = []
    if keywords:
        kw_list = [
            re.sub(r'<[^>]+>', '', li).strip()
            for li in re.findall(r'<rdf:li[^>]*>(.*?)</rdf:li>', keywords[0], re.I | re.S)
        ]
    captured = ''
    m = re.search(r'<photoshop:DateCreated>(.*?)</photoshop:DateCreated>', xmp, re.I | re.S)
    if m:
        captured = re.sub(r'<[^>]+>', '', m.group(1)).strip()[:10]
    return normalize_display({
        'title': title,
        'description': description,
        'keywords': kw_list,
        'captured_at': captured,
    })


def read_still_display(path: Path) -> Dict[str, Any]:
    data = path.read_bytes()
    display = parse_xmp_display(extract_xmp_from_bytes(data))
    if not display.get('captured_at'):
        captured = read_exif_captured_at(path)
        if captured:
            display['captured_at'] = captured
    return display


def write_still_display(path: Path, display: Dict[str, Any]) -> None:
    ext = path.suffix.lower()
    if ext in {'.jpg', '.jpeg'}:
        write_jpeg_xmp(path, display)
    elif ext == '.png':
        write_png_xmp(path, display)
    elif ext == '.webp':
        write_webp_xmp(path, display)
    else:
        raise RuntimeError(f'Unsupported still master format: {ext}')


def write_video_display(path: Path, display: Dict[str, Any]) -> None:
    ffmpeg = resolve_ffmpeg()
    if not ffmpeg:
        raise RuntimeError('ffmpeg is required to write Matroska tags on video masters')
    title = display.get('title') or ''
    description = display.get('description') or ''
    keywords = ', '.join(display.get('keywords') or [])
    captured = display.get('captured_at') or ''
    tmp_dir = Path(tempfile.mkdtemp(prefix='bp-mkv-'))
    try:
        tmp = tmp_dir / (path.stem + '.tagged.mkv')
        cmd = [
            ffmpeg, '-y', '-hide_banner', '-loglevel', 'error',
            '-i', str(path),
            '-map', '0',
            '-c', 'copy',
            '-metadata', f'title={title}',
            '-metadata', f'description={description}',
            '-metadata', f'COMMENT={keywords}',
        ]
        if captured:
            cmd.extend(['-metadata', f'date={captured}'])
        cmd.append(str(tmp))
        proc = subprocess.run(
            cmd,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            universal_newlines=True,
            encoding='utf-8',
            errors='replace',
        )
        if proc.returncode != 0 or not tmp.is_file():
            detail = (proc.stderr or proc.stdout or '').strip()
            raise RuntimeError(detail or 'ffmpeg Matroska tag write failed')
        os.replace(tmp, path)
    finally:
        shutil.rmtree(tmp_dir, ignore_errors=True)


def read_video_display(path: Path) -> Dict[str, Any]:
    ffprobe = resolve_ffprobe()
    if not ffprobe:
        return normalize_display({})
    cmd = [
        ffprobe, '-v', 'quiet', '-print_format', 'json',
        '-show_format', str(path),
    ]
    proc = subprocess.run(
        cmd,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        universal_newlines=True,
        encoding='utf-8',
        errors='replace',
    )
    if proc.returncode != 0:
        return normalize_display({})
    try:
        payload = json.loads(proc.stdout or '{}')
    except json.JSONDecodeError:
        return normalize_display({})
    tags = ((payload.get('format') or {}).get('tags') or {})
    if not isinstance(tags, dict):
        tags = {}
    # Matroska / ffmpeg tag keys vary in case.
    lowered = {str(k).lower(): str(v) for k, v in tags.items()}
    keywords_raw = lowered.get('comment') or lowered.get('keywords') or ''
    return normalize_display({
        'title': lowered.get('title') or '',
        'description': lowered.get('description') or lowered.get('synopsis') or '',
        'keywords': keywords_raw,
        'captured_at': (lowered.get('date') or lowered.get('creation_time') or '')[:10],
    })


def action_write(payload: Dict[str, Any]) -> Dict[str, Any]:
    asset_id = str(payload.get('asset_id') or '').strip()
    asset = load_asset(asset_id)
    if not asset or str(asset.get('kind') or '') != 'visual':
        return {'ok': False, 'error': 'Visual asset not found'}
    display = normalize_display(payload.get('display'))
    path = master_path_for_asset(asset)
    if not path.is_file():
        return {'ok': False, 'error': f'Master file missing: {path.name}'}
    media_type = str(asset.get('media_type') or '').strip().lower()
    ext = path.suffix.lower()
    try:
        if media_type == 'video' or ext in VIDEO_EXTS:
            write_video_display(path, display)
        elif ext in STILL_EXTS:
            write_still_display(path, display)
        else:
            return {'ok': False, 'error': f'Unsupported master format for metadata: {ext}'}
    except Exception as exc:
        return {'ok': False, 'error': str(exc)}
    return {'ok': True, 'asset_id': asset_id, 'master': path.name, 'display': display}


def action_read(payload: Dict[str, Any]) -> Dict[str, Any]:
    asset_id = str(payload.get('asset_id') or '').strip()
    asset = load_asset(asset_id)
    if not asset or str(asset.get('kind') or '') != 'visual':
        return {'ok': False, 'error': 'Visual asset not found'}
    path = master_path_for_asset(asset)
    if not path.is_file():
        return {'ok': False, 'error': f'Master file missing: {path.name}'}
    media_type = str(asset.get('media_type') or '').strip().lower()
    ext = path.suffix.lower()
    if media_type == 'video' or ext in VIDEO_EXTS:
        display = read_video_display(path)
    elif ext in STILL_EXTS:
        display = read_still_display(path)
    else:
        return {'ok': False, 'error': f'Unsupported master format for metadata: {ext}'}
    return {'ok': True, 'asset_id': asset_id, 'display': display}


def action_heal_empty(payload: Dict[str, Any]) -> Dict[str, Any]:
    """Fill empty registry display fields from master embeds. Does not overwrite non-empty fields."""
    registry = load_registry()
    assets = registry.get('assets') if isinstance(registry.get('assets'), dict) else {}
    limit_id = str(payload.get('asset_id') or '').strip()
    healed = []
    for asset_id, asset in assets.items():
        if not isinstance(asset, dict) or str(asset.get('kind') or '') != 'visual':
            continue
        if limit_id and asset_id != limit_id:
            continue
        current = normalize_display(asset.get('display'))
        path = master_path_for_asset(asset)
        if not path.is_file():
            continue
        media_type = str(asset.get('media_type') or '').strip().lower()
        ext = path.suffix.lower()
        try:
            if media_type == 'video' or ext in VIDEO_EXTS:
                embedded = read_video_display(path)
            elif ext in STILL_EXTS:
                embedded = read_still_display(path)
            else:
                continue
        except Exception:
            continue
        merged = dict(current)
        changed = False
        for key in ('title', 'description', 'captured_at'):
            if not merged.get(key) and embedded.get(key):
                merged[key] = embedded[key]
                changed = True
        if not merged.get('keywords') and embedded.get('keywords'):
            merged['keywords'] = embedded['keywords']
            changed = True
        if not changed:
            continue
        assets[asset_id]['display'] = merged
        healed.append(asset_id)
    if healed:
        registry['assets'] = assets
        ASSET_REGISTRY_FILE.parent.mkdir(parents=True, exist_ok=True)
        tmp = ASSET_REGISTRY_FILE.with_suffix('.json.tmp')
        with open(tmp, 'w', encoding='utf-8') as handle:
            json.dump(registry, handle, ensure_ascii=False, indent=2)
            handle.write('\n')
        os.replace(tmp, ASSET_REGISTRY_FILE)
    return {'ok': True, 'healed': healed, 'count': len(healed)}


def main() -> None:
    payload = read_payload()
    action = str(payload.get('action') or 'write').strip().lower()
    if action == 'write':
        result = action_write(payload)
        respond(result, 0 if result.get('ok') else 1)
    if action == 'read':
        result = action_read(payload)
        respond(result, 0 if result.get('ok') else 1)
    if action in {'heal', 'heal_empty'}:
        respond(action_heal_empty(payload))
    respond({'ok': False, 'error': f'Unknown action: {action}'}, 1)


if __name__ == '__main__':
    main()
