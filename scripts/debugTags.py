"""
Debug script to inspect FLAC and MP3 tags
Shows all available tags in FLAC, ID3v2, and APEv2 formats
"""

import os
import sys
from pathlib import Path
from mutagen.flac import FLAC
from mutagen.id3 import ID3
from mutagen.apev2 import APEv2
from mutagen import File

# Get current directory (original folder)
orig_dir = Path.cwd()

# Find FLAC and MP3 files
flac_files = sorted(list(set(orig_dir.glob('*.flac'))))
mp3_files = sorted(list(set(orig_dir.glob('*.mp3'))))

print("=" * 70)
print("📁 Tag Inspector - Show all tags in audio files")
print("=" * 70)

if flac_files:
    print(f"\n🎵 FLAC files found: {len(flac_files)}")
    for i, f in enumerate(flac_files[:5]):
        print(f"  {i+1}. {f.name}")
    if len(flac_files) > 5:
        print(f"  ... and {len(flac_files)-5} more")

if mp3_files:
    print(f"\n🎵 MP3 files found: {len(mp3_files)}")
    for i, f in enumerate(mp3_files[:5]):
        print(f"  {i+1}. {f.name}")
    if len(mp3_files) > 5:
        print(f"  ... and {len(mp3_files)-5} more")

print("\n" + "=" * 70)

# Default to first MP3 if available, otherwise first FLAC
if mp3_files:
    target_file = mp3_files[0]
    print(f"\n📀 Inspecting: {target_file.name} (MP3)")
    
    print("\n🏷️  ID3v2 TAGS:\n")
    try:
        id3 = ID3(str(target_file))
        if id3:
            for tag_name in sorted(id3.keys()):
                tag_value = id3[tag_name]
                display_value = str(tag_value)[:100]
                print(f"  {tag_name:20} = {display_value}")
        else:
            print("  No ID3 tags found")
    except Exception as e:
        print(f"  Error reading ID3: {e}")
    
    print("\n🏷️  APEv2 TAGS:\n")
    try:
        ape = APEv2(str(target_file))
        if ape:
            for tag_name in sorted(ape.keys()):
                tag_value = ape[tag_name]
                display_value = str(tag_value)[:100]
                print(f"  {tag_name:20} = {display_value}")
        else:
            print("  No APE tags found")
    except Exception as e:
        print(f"  (No APE tags or error: {e})")

elif flac_files:
    target_file = flac_files[0]
    print(f"\n📀 Inspecting: {target_file.name} (FLAC)")
    
    print("\n🏷️  FLAC TAGS:\n")
    try:
        audio = FLAC(str(target_file))
        if audio.tags:
            for tag_name in sorted(audio.tags.keys()):
                tag_value = audio.tags[tag_name]
                if isinstance(tag_value, list) and tag_value:
                    display_value = str(tag_value[0])[:100]
                else:
                    display_value = str(tag_value)[:100]
                print(f"  {tag_name:20} = {display_value}")
        else:
            print("  No tags found")
    except Exception as e:
        print(f"  Error reading FLAC: {e}")

else:
    print("\n❌ No audio files found (FLAC or MP3) in current directory")
    sys.exit(1)

print("\n" + "=" * 70)
print("🖼️  EMBEDDED ARTWORK CHECK:\n")

# Check for embedded artwork
try:
    audio = File(str(target_file))
    if audio is not None:
        has_artwork = False
        
        # Check MP3 APIC frames
        if hasattr(audio, 'tags') and audio.tags:
            for key in audio.tags.keys():
                if key.startswith('APIC') or key.startswith('APIC:'):
                    apic = audio.tags[key]
                    data_size = len(getattr(apic, 'data', b''))
                    mime = getattr(apic, 'mime', 'image/jpeg')
                    desc = getattr(apic, 'desc', 'no description')
                    print(f"  ✓ ID3 APIC found: {mime} ({data_size} bytes)")
                    print(f"    Description: {desc}")
                    has_artwork = True
        
        # Check FLAC pictures
        if hasattr(audio, 'pictures'):
            pics = getattr(audio, 'pictures', [])
            if pics:
                for i, pic in enumerate(pics, 1):
                    data_size = len(getattr(pic, 'data', b''))
                    mime = getattr(pic, 'mime', 'image/jpeg')
                    pic_type = getattr(pic, 'type', 0)
                    print(f"  ✓ FLAC Picture #{i} found: {mime} ({data_size} bytes)")
                    print(f"    Type: {pic_type}")
                    has_artwork = True
        
        if not has_artwork:
            print("  ✗ No embedded artwork found")
    else:
        print("  ✗ Could not read file")
except Exception as e:
    print(f"  ✗ Error checking artwork: {e}")

print("\n" + "=" * 70)
print("\n✅ Use this information to update optimizeMedia.py tag mappings")
print("   Look for mismatches between FLAC and ID3/APE tag names")
print("   Embedded artwork will be extracted to <filename>.jpg/png")

