#!/bin/bash
# Post-build font fix: replace NzYEeyps with Bgl3Icaq and font-display:swap with block
# This fixes Material Symbols icon rendering issue with npm build

DIST_DIR="/home/gdq/dist"
CSS_FILE=$(grep -o 'assets/index-[a-zA-Z0-9_-]*\.css' "$DIST_DIR/index.html" | head -1)
if [ -n "$CSS_FILE" ]; then
  CSS_PATH="$DIST_DIR/$CSS_FILE"
  if [ -f "$CSS_PATH" ]; then
    sed -i 's/material-symbols-outlined-NzYEeyps\.woff2/material-symbols-outlined-Bgl3Icaq.woff2/g' "$CSS_PATH"
    sed -i 's/font-display:swap/font-display:block/g' "$CSS_PATH"
    echo "Fixed font in $CSS_FILE"
  fi
fi
