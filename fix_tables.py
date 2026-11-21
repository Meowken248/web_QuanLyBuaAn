import os
import re

directory = 'c:/wamp64/www/web_QuanLyBuaAn/user'
for root, _, files in os.walk(directory):
    for file in files:
        if file.endswith('.php'):
            filepath = os.path.join(root, file)
            with open(filepath, 'r', encoding='utf-8') as f:
                content = f.read()
            
            pattern = re.compile(r'<table class="(table [^"]*)">')
            
            def replacer(match):
                classes = match.group(1)
                if 'text-nowrap' not in classes:
                    return f'<table class="{classes} text-nowrap">'
                return match.group(0)
            
            new_content = pattern.sub(replacer, content)
            
            if new_content != content:
                with open(filepath, 'w', encoding='utf-8') as f:
                    f.write(new_content)
                print(f'Updated {file}')
print('Done')
