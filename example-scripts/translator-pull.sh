#!/bin/bash

# stáhne překlady z oneSkyApp pro všechny jazyky
php web/index.php kdyby:translation-extract --catalogue-language="cs" --output-format="po" --exclude-prefix-file="./translator-exclude-prefix" --onesky-download
php web/index.php kdyby:translation-extract --catalogue-language="en" --output-format="po" --exclude-prefix-file="./translator-exclude-prefix" --onesky-download
php web/index.php kdyby:translation-extract --catalogue-language="fr" --output-format="po" --exclude-prefix-file="./translator-exclude-prefix" --onesky-download
php web/index.php kdyby:translation-extract --catalogue-language="de" --output-format="po" --exclude-prefix-file="./translator-exclude-prefix" --onesky-download
