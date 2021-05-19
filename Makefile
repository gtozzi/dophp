# Utility dist tasks

# Update language files
lang: locale
	echo '<?php' > /tmp/dophp_tpl_strings.php
	grep -REoh "_\('[^']+'\)" tpl >> /tmp/dophp_tpl_strings.php
	find . pages widgets /tmp/dophp_tpl_strings.php -maxdepth 1 -type f -iname "*.php" | xargs xgettext --from-code=UTF-8 -o /tmp/dophp.po
	msgmerge -N locale/it_IT/LC_MESSAGES/dophp.po /tmp/dophp.po > /tmp/dophp_new.po
	cat /tmp/dophp_new.po > locale/it_IT/LC_MESSAGES/dophp.po

# Compile language files
langbuild: locale
	msgfmt --statistics -c locale/it_IT/LC_MESSAGES/dophp.po \
	-o locale/it_IT/LC_MESSAGES/dophp.mo
	msgfmt --statistics -c locale/en_US/LC_MESSAGES/dophp.po \
	-o locale/en_US/LC_MESSAGES/dophp.mo
	msgfmt --statistics -c locale/en_GB/LC_MESSAGES/dophp.po \
	-o locale/en_GB/LC_MESSAGES/dophp.mo
