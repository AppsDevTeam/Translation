#!/bin/bash

# nahraje překlady na oneSkyApp pro cs
php web/index.php kdyby:translation-extract --catalogue-language="cs" --output-format="po" --exclude-prefix-file="./translator-exclude-prefix" --onesky-upload
