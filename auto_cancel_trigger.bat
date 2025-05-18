@echo off
cd "C:\xampp\htdocs\event"
"C:\xampp\php\php.exe" -f "C:\xampp\htdocs\event\auto_cancel_unpaid_bookings.php" > auto_cancel_output.log 2>&1 