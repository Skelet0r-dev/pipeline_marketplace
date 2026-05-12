import os

emoji_map = {
    '🛍': '<i class="bi bi-bag"></i>',
    '🏪': '<i class="bi bi-shop"></i>',
    '👤': '<i class="bi bi-person"></i>',
    '🔖': '<i class="bi bi-bookmark-fill"></i>',
    '📑': '<i class="bi bi-file-earmark"></i>',
    '🔔': '<i class="bi bi-bell"></i>',
    '💖': '<i class="bi bi-heart-fill"></i>',
    '🚪': '<i class="bi bi-box-arrow-right"></i>',
    '📍': '<i class="bi bi-geo-alt"></i>',
    '💳': '<i class="bi bi-credit-card"></i>',
    '🧾': '<i class="bi bi-receipt"></i>',
    '📅': '<i class="bi bi-calendar"></i>',
    '💬': '<i class="bi bi-chat"></i>',
    '✕': '<i class="bi bi-x"></i>',
    '👍': '<i class="bi bi-hand-thumbs-up"></i>',
    '😍': '<i class="bi bi-emoji-heart-eyes"></i>',
    '👎': '<i class="bi bi-hand-thumbs-down"></i>',
    '✗': '<i class="bi bi-x"></i>',
    '✓': '<i class="bi bi-check"></i>',
    '🔍': '<i class="bi bi-search"></i>',
    '📩': '<i class="bi bi-envelope"></i>',
    '✅': '<i class="bi bi-check-circle-fill"></i>',
    '⚠️': '<i class="bi bi-exclamation-triangle-fill"></i>',
    '⚠': '<i class="bi bi-exclamation-triangle-fill"></i>',
    '🚨': '<i class="bi bi-exclamation-octagon-fill"></i>',
    'ℹ️': '<i class="bi bi-info-circle-fill"></i>',
    'ℹ': '<i class="bi bi-info-circle-fill"></i>',
    '📊': '<i class="bi bi-bar-chart-fill"></i>',
    '📄': '<i class="bi bi-file-earmark-text"></i>',
    '🔒': '<i class="bi bi-lock-fill"></i>'
}

# Add variations with \ufe0f just in case
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

css_link = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">'

for root, dirs, files in os.walk("."):
    if ".git" in root or "scratch" in root: continue
    for file in files:
        if not file.endswith(".php") and not file.endswith(".html") and not file.endswith(".js"): continue
        path = os.path.join(root, file)
        
        try:
            with open(path, "r", encoding="utf-8") as f:
                content = f.read()
            
            new_content = replace_emojis_in_text(content)
            
            # If it's a php or html file and it has </head> but doesn't have the bootstrap icons link, insert it
            if (file.endswith(".php") or file.endswith(".html")) and ("</head>" in new_content) and ("bootstrap-icons.min.css" not in new_content):
                new_content = new_content.replace("</head>", f"    {css_link}\n</head>")
            
            if new_content != content:
                with open(path, "w", encoding="utf-8") as f:
                    f.write(new_content)
                print(f"Updated {path}")
        except Exception as e:
            pass

