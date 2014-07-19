#!/bin/bash -x

FILES=`find japanese-utf8.templates -name '*ml'`

for utf8file in $FILES
do
	eucfile=`echo $utf8file | sed 's/japanese-utf8/japanese-euc/'`
	nkf -e -W -d < $utf8file > $eucfile
done

nkf -e -W -d < japanese-utf8.help.html > japanese-euc.help.html
