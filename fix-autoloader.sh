#!/bin/bash
cd ~/convoca-enroll

python3 << 'PYTHONEND'
with open("convoca-enroll.php", "r") as f:
    content = f.read()

# Fix: add str_replace for backslash to slash in the autoloader
old = '''$relative = strtolower( str_replace( '_', '-', $relative ) );

		foreach ( array( 'includes/', 'admin/', 'public/', 'media/' ) as $dir ) {'''

new = '''$relative = strtolower( str_replace( '_', '-', $relative ) );
		$relative = str_replace( '\\\\', '/', $relative ); // Convert sub-namespace separators.

		foreach ( array( 'includes/', 'admin/', 'public/', 'media/' ) as $dir ) {'''

content = content.replace(old, new)

with open("convoca-enroll.php", "w") as f:
    f.write(content)

print("Autoloader fixed")
PYTHONEND

php -l convoca-enroll.php
