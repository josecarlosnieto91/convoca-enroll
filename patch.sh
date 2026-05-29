#!/bin/bash
cd ~/convoca-enroll

# 1. Add 'media/' to autoloader directories
sed -i "s|foreach ( array( 'includes/', 'admin/', 'public/' )|foreach ( array( 'includes/', 'admin/', 'public/', 'media/' )|" convoca-enroll.php

# 2. Add Media installer initialization after the settings check, inside activation hook
# Find the line "if ( false === get_option( 'conv_enroll_settings' ) ) {" and add before it
sed -i "/if ( false === get_option( 'conv_enroll_settings' ) )/i\\
\t\\t// Media & Social Suite tables.\\n\\t\\tMedia\\\\Media_Installer::install();\\n\\t\\tMedia\\\\Media_Capabilities::ensure();
" convoca-enroll.php

# 3. Add Media bootstrap after Enroll_Upgrade_Manager
sed -i "/new Convoca\\\\Enroll\\\\Enroll_Upgrade_Manager();/a\\
\\n\\t\\t// Media & Social Suite.\\n\\t\\tnew Convoca\\\\Enroll\\\\Media\\\\Media_Upgrade_Manager();\\n\\t\\tnew Convoca\\\\Enroll\\\\Media\\\\Media_Rest_API();
" convoca-enroll.php

echo "Patched convoca-enroll.php"
grep -n "media\|Media_" convoca-enroll.php | head -15
