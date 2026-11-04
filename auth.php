<?php
// Preserve existing links while the account page is named login.php.
header('Location: login.php', true, 302);
exit;
