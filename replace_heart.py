import os

target = '<a href="edit_profile.php?tab=support" class="dropdown-item-custom"><span class="item-icon"><i class="bi bi-heart-fill"></i></span> Support Us</a>'
replacement = '<a href="edit_profile.php?tab=support" class="dropdown-item-custom"><span class="item-icon"><i class="bi bi-heart-fill" style="color: #22c55e;"></i></span> Support Us</a>'

files_to_check = [
    "public_profile.php",
    "browse.php",
    "notifications.php",
    "listing.php",
    "edit_profile.php",
    "storefront.php",
    "saved_listings.php",
    "dashboard.php"
]

for file in files_to_check:
    if os.path.exists(file):
        with open(file, 'r', encoding='utf-8') as f:
            content = f.read()
        
        if target in content:
            content = content.replace(target, replacement)
            with open(file, 'w', encoding='utf-8') as f:
                f.write(content)
            print(f"Updated {file}")

