#!/bin/bash

# stáhne překlady z oneSkyApp pro češtinu
php web/index.php kdyby:translation-extract --catalogue-language="cs" --output-format="po" --exclude-prefix-file="./translator-exclude-prefix" --onesky-download

# vytáhne překlady ze zdrojáků a ty ještě nepřeložené vloží do českého souboru
php web/index.php kdyby:translation-extract --catalogue-language="cs" --output-format="po" --exclude-prefix-file="./translator-exclude-prefix"
