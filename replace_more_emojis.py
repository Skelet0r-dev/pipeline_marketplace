import os

emoji_map = {
    '👁': '<i class="bi bi-eye"></i>',
    '🙈': '<i class="bi bi-eye-slash"></i>',
    '🎉': '<i class="bi bi-party-popper"></i>',
    '👕': '<i class="bi bi-tag"></i>',
    '💻': '<i class="bi bi-laptop"></i>',
    '📚': '<i class="bi bi-book"></i>',
    '🎨': '<i class="bi bi-palette"></i>',
    '🎟': '<i class="bi bi-ticket-perforated"></i>',
    '🔬': '<i class="bi bi-journal-text"></i>',
    '🍪': '<i class="bi bi-basket"></i>',
    '📦': '<i class="bi bi-box"></i>',
    '✦': '<i class="bi bi-stars"></i>',
    '🎒': '<i class="bi bi-backpack"></i>',
    '🏢': '<i class="bi bi-building"></i>',
    '✔': '<i class="bi bi-check"></i>',
    '✘': '<i class="bi bi-x"></i>',
    '👗': '<i class="bi bi-tag"></i>',
    '🎸': '<i class="bi bi-music-note"></i>',
    '🧪': '<i class="bi bi-journal-text"></i>',
    '🎓': '<i class="bi bi-mortarboard"></i>',
    '🏷': '<i class="bi bi-tag"></i>',
    '✏': '<i class="bi bi-pencil"></i>',
    '🐇': '<i class="bi bi-bicycle"></i>',
    '🖼': '<i class="bi bi-image"></i>',
    '🗑': '<i class="bi bi-trash"></i>',
    '👥': '<i class="bi bi-people"></i>',
    '📋': '<i class="bi bi-clipboard"></i>',
    '🔐': '<i class="bi bi-lock-fill"></i>',
    '🤖': '<i class="bi bi-robot"></i>'
}

emoji_map_with_variants = {}
for k, v in emoji_map.items():
    emoji_map_with_variants[k] = v
    if not k.endswith('\ufe0f'):
        emoji_map_with_variants[k + '\ufe0f'] = v

def replace_emojis_in_text(text):
    # Sort keys by length so longer sequences are matched first
    keys = sorted(emoji_map_with_variants.keys(), key=len, reverse=True)
    for k in keys:
        text = text.replace(k, emoji_map_with_variants[k])
    return text

for root, dirs, files in os.walk("."):
    if ".git" in root or "scratch" in root: continue
    for file in files:
        if not file.endswith(".php") and not file.endswith(".html") and not file.endswith(".js"): continue
        path = os.path.join(root, file)
        
        try:
            with open(path, "r", encoding="utf-8") as f:
                content = f.read()
            
            new_content = replace_emojis_in_text(content)
            
            if new_content != content:
                with open(path, "w", encoding="utf-8") as f:
                    f.write(new_content)
                print(f"Updated {path}")
        except Exception as e:
            pass

