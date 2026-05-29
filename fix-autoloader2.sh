#!/bin/bash
cd ~/convoca-enroll

python3 << 'PYTHONEND'
with open("convoca-enroll.php", "r") as f:
    content = f.read()

# Better approach: use basename of the relative path after converting slashes
old = '''$relative = strtolower( str_replace( '_', '-', $relative ) );
		$relative = str_replace( '\\\\', '/', $relative ); // Convert sub-namespace separators.

		foreach ( array( 'includes/', 'admin/', 'public/', 'media/' ) as $dir ) {'''

new = '''$relative = strtolower( str_replace( '_', '-', $relative ) );
		$relative = str_replace( '\\\\', '/', $relative ); // Convert sub-namespace separators.
		$class_name = basename( $relative ); // Use only the last segment for WP convention.

		foreach ( array( 'includes/', 'admin/', 'public/', 'media/' ) as $dir ) {'''

content = content.replace(old, new)

# Also fix the wp_file reference to use $class_name instead of $relative
old2 = "$wp_file = CONV_ENROLL_DIR . \$dir . 'class-' . \$relative . '.php';"
new2 = "$wp_file = CONV_ENROLL_DIR . \$dir . 'class-' . \$class_name . '.php';"

content = content.replace(old2, new2)

with open("convoca-enroll.php", "w") as f:
    f.write(content)

print("Autoloader v2 fixed")
PYTHONEND

php -l convoca-enroll.php
