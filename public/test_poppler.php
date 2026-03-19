<?php
$cmd = '"C:/poppler/Library/bin/pdftoppm.exe" -h 2>&1';
echo nl2br(shell_exec($cmd));
