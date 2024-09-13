<?php
// ฟังก์ชันง่าย ๆ สำหรับโหลดไฟล์ .env และใส่ตัวแปรลงใน environment
function loadEnv($path)
{
    if (!file_exists($path)) {
        throw new \InvalidArgumentException("$path ไม่พบไฟล์ .env");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // ข้ามบรรทัดที่เป็นคอมเมนต์
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // กำหนด environment variables
        putenv(sprintf('%s=%s', $name, $value));
        $_ENV[$name] = $value;  // อาจไม่ทำงานในบางเซิร์ฟเวอร์ ขึ้นอยู่กับ php.ini
        $_SERVER[$name] = $value; // เพิ่มเพื่อรองรับ $_SERVER ด้วย
    }
}

// ใช้งานฟังก์ชัน
loadEnv(__DIR__ . '/../.env');

// ตัวอย่างการเข้าถึงตัวแปรในไฟล์ .env
$servername = getenv('DB_SERVERNAME');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');

// การเชื่อมต่อฐานข้อมูล
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
