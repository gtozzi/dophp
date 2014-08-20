# Utility dist tasks

# Update language files
lang: locale
	xgettext --from-code=UTF-8 -o /tmp/dophp.po *.php
	msgmerge -N locale/it_IT/LC_MESSAGES/dophp.po /tmp/dophp.po > /tmp/dophp_new.po
	cat /tmp/dophp_new.po > locale/it_IT/LC_MESSAGES/dophp.po

# Compile language files
langbuild: locale
	msgfmt --statistics -c locale/it_IT/LC_MESSAGES/dophp.po \
	-o locale/it_IT/LC_MESSAGES/dophp.mo
