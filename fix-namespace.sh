#!/bin/bash
cd ~/convoca-enroll

# Fix the Media namespace references - they need full path
sed -i "s/Media\\Media_Installer::install();/Convoca\\Enroll\\Media\\Media_Installer::install();/" convoca-enroll.php
sed -i "s/Media\\Media_Capabilities::ensure();/Convoca\\Enroll\\Media\\Media_Capabilities::ensure();/" convoca-enroll.php

grep -n "Media_Installer\|Media_Capabilities" convoca-enroll.php
