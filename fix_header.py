import os

with open('includes/header.php', 'r', encoding='utf-8') as f:
    content = f.read()

start_col = content.find('<div class="collapse navbar-collapse"')
start_flex = content.find('<div class="d-flex align-items-center">', start_col)
end_flex_index = content.find('</div>\r\n        </div>\r\n    </div>\r\n</nav>')

if end_flex_index == -1:
    end_flex_index = content.find('</div>\n        </div>\n    </div>\n</nav>')

if start_col != -1 and start_flex != -1 and end_flex_index != -1:
    flex_content = content[start_flex:end_flex_index].strip()
    flex_content_new = flex_content.replace('<div class="d-flex align-items-center">', '<div class="d-flex align-items-center ms-auto order-lg-last">')

    content_without_flex = content[:start_flex] + content[end_flex_index:]

    btn_start = content_without_flex.find('<button class="navbar-toggler"')
    
    final_content = content_without_flex[:btn_start] + flex_content_new + '\n        ' + content_without_flex[btn_start:]
    final_content = final_content.replace('<button class="navbar-toggler"', '<button class="navbar-toggler ms-2"')

    with open('includes/header.php', 'w', encoding='utf-8') as f:
        f.write(final_content)
    print("Success")
else:
    print("Failed to find indices", start_col, start_flex, end_flex_index)
