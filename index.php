<?php
// --- 1. ส่วนเชื่อมต่อฐานข้อมูล ---
$host     = getenv('DB_HOST') ?: "ron-bstmariadb-aqhiz8"; 
$username = getenv('DB_USER') ?: "tree_db";
$password = getenv('DB_PASS') ?: "Ron_2006";
$dbname   = getenv('DB_NAME') ?: "ron";

$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    $db_status = "❌ Connection Failed: " . $conn->connect_error;
} else {
    $conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
    $conn->select_db($dbname);
    
    $sql_create_table = "CREATE TABLE IF NOT EXISTS bst_nodes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        node_value INT NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($sql_create_table);
    $db_status = "✅ Connected to MariaDB ($host)";
}

// 3. จัดการข้อมูล (Add/Delete) - ป้องกันเลข 0
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_val']) && $_POST['add_val'] !== "") {
        $val = (int)$_POST['add_val'];
        $conn->query("INSERT INTO bst_nodes (node_value) VALUES ($val)");
    } elseif (isset($_POST['clear_all'])) {
        $conn->query("TRUNCATE TABLE bst_nodes"); // ลบข้อมูลทั้งหมดในตาราง
    }
    header("Location: " . $_SERVER['PHP_SELF']); 
    exit();
}

// 4. ดึงข้อมูลมาวาดต้นไม้
$db_nodes = [];
$res = $conn->query("SELECT node_value FROM bst_nodes ORDER BY id ASC");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $db_nodes[] = (int)$row['node_value'];
    }
} else {
    $db_nodes = []; // ถ้าว่างก็เริ่มจากว่างจริง
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Midnight Forest - Connected Binary Tree</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;500&display=swap" rel="stylesheet">
    <style>
        /* CSS เดิมของคุณ (สวยอยู่แล้ว คงไว้ทั้งหมด) */
        :root {
            --bg-color: #0f172a; --card-bg: #1e293b; --primary-glow: #22d3ee;
            --secondary-glow: #818cf8; --text-color: #f1f5f9; --accent: #fb7185;
            --sub-text: #94a3b8; --line-color: rgba(34, 211, 238, 0.4);
        }
        body { font-family: 'Kanit', sans-serif; background-color: var(--bg-color); color: var(--text-color); margin: 0; display: flex; flex-direction: column; align-items: center; min-height: 100vh; padding-bottom: 50px; }
        header { text-align: center; padding: 2rem; }
        h1 { margin: 0; font-size: 2.2rem; color: var(--primary-glow); text-shadow: 0 0 15px var(--primary-glow); }
        .control-panel { background: var(--card-bg); padding: 15px 25px; border-radius: 50px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); margin-bottom: 25px; display: flex; gap: 12px; border: 1px solid rgba(255,255,255,0.1); }
        input { padding: 10px; border-radius: 25px; border: 2px solid var(--secondary-glow); background: #0f172a; color: white; outline: none; width: 100px; text-align: center; }
        button { padding: 10px 20px; border-radius: 25px; border: none; cursor: pointer; font-weight: bold; transition: 0.3s; }
        .btn-add { background: var(--primary-glow); color: #0f172a; }
        .btn-clear { background: var(--accent); color: white; }
        button:hover { transform: scale(1.05); }
        .visualizer-container { width: 90%; max-width: 800px; height: 400px; background: rgba(30, 41, 59, 0.4); border-radius: 20px; position: relative; margin-bottom: 30px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05); }
        #treeLines { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; }
        #treeNodes { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; }
        .node { width: 40px; height: 40px; background: var(--secondary-glow); border-radius: 50%; display: flex; align-items: center; justify-content: center; position: absolute; font-weight: bold; box-shadow: 0 0 15px var(--secondary-glow); transform: translate(-50%, -50%); animation: grow 0.4s ease-out; z-index: 2; }
        @keyframes grow { from { transform: translate(-50%, -50%) scale(0); } to { transform: translate(-50%, -50%) scale(1); } }
        .output-box { width: 85%; max-width: 700px; background: var(--card-bg); padding: 20px; border-radius: 15px; margin-bottom: 30px; }
        .output-item { margin-bottom: 8px; font-size: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 5px; }
        .output-item span { color: var(--primary-glow); font-weight: bold; margin-right: 10px; }
        .db-badge { font-size: 0.8rem; color: #10b981; margin-bottom: 10px; }
    </style>
</head>
<body>

    <header>
        <h1>🌲 Midnight Forest</h1>
        <div class="db-badge"><?php echo $db_status; ?></div>
    </header>

    <form id="dbForm" method="POST" style="display:none;">
        <input type="hidden" name="add_val" id="add_val">
        <input type="hidden" name="clear_all" id="clear_all">
    </form>

    <div class="control-panel">
        <input type="number" id="nodeValue" placeholder="เลข Node">
        <button class="btn-add" onclick="submitAdd()">ปลูกกิ่ง</button>
        <button class="btn-clear" onclick="submitClear()">ถางป่า</button>
    </div>

    <div class="visualizer-container">
        <svg id="treeLines"></svg>
        <div id="treeNodes"></div>
    </div>

    <div class="output-box">
        <div class="output-item"><span>Preorder:</span> <span id="preorder">-</span></div>
        <div class="output-item"><span>Inorder:</span> <span id="inorder">-</span></div>
        <div class="output-item"><span>Postorder:</span> <span id="postorder">-</span></div>
    </div>

    <script>
        class Node {
            constructor(val, x, y) {
                this.val = val; this.x = x; this.y = y;
                this.left = null; this.right = null;
            }
        }

        let root = null;
        const verticalSpacing = 70;

        // ฟังก์ชันส่งค่าไปบันทึกใน MariaDB
        function submitAdd() {
            const val = document.getElementById('nodeValue').value;
            if (val === "") return alert("กรุณากรอกตัวเลข");
            document.getElementById('add_val').value = val;
            document.getElementById('dbForm').submit();
        }

        function submitClear() {
            if (confirm("ต้องการลบต้นไม้ทั้งหมดใช่ไหม?")) {
                document.getElementById('clear_all').value = "true";
                document.getElementById('dbForm').submit();
            }
        }

        // ฟังก์ชันวาด (Logic เดิมของคุณแต่ปรับให้รองรับการโหลดจาก DB)
        function insertLogic(val) {
            if (!root) {
                root = new Node(val, 400, 50);
            } else {
                insertRecursive(root, val, 400, 50, 200);
            }
        }

        function insertRecursive(node, val, x, y, offset) {
            if (val < node.val) {
                if (!node.left) node.left = new Node(val, x - offset, y + verticalSpacing);
                else insertRecursive(node.left, val, x - offset, y + verticalSpacing, offset / 1.8);
            } else if (val > node.val) {
                if (!node.right) node.right = new Node(val, x + offset, y + verticalSpacing);
                else insertRecursive(node.right, val, x + offset, y + verticalSpacing, offset / 1.8);
            }
        }

        function render() {
            const nodesContainer = document.getElementById('treeNodes');
            const linesContainer = document.getElementById('treeLines');
            nodesContainer.innerHTML = '';
            linesContainer.innerHTML = '';
            if (root) drawTree(root, linesContainer, nodesContainer);
            updateOrderText();
        }

        function drawTree(node, lines, nodes) {
            if (node.left) {
                lines.innerHTML += `<line x1="${node.x}" y1="${node.y}" x2="${node.left.x}" y2="${node.left.y}" stroke="rgba(34, 211, 238, 0.4)" stroke-width="2" />`;
                drawTree(node.left, lines, nodes);
            }
            if (node.right) {
                lines.innerHTML += `<line x1="${node.x}" y1="${node.y}" x2="${node.right.x}" y2="${node.right.y}" stroke="rgba(34, 211, 238, 0.4)" stroke-width="2" />`;
                drawTree(node.right, lines, nodes);
            }
            const div = document.createElement('div');
            div.className = 'node';
            div.innerText = node.val;
            div.style.left = node.x + 'px';
            div.style.top = node.y + 'px';
            nodes.appendChild(div);
        }

        function updateOrderText() {
            document.getElementById('preorder').innerText = getPre(root).join(' → ') || '-';
            document.getElementById('inorder').innerText = getIn(root).join(' → ') || '-';
            document.getElementById('postorder').innerText = getPost(root).join(' → ') || '-';
        }

        function getPre(n, l=[]) { if(n){ l.push(n.val); getPre(n.left, l); getPre(n.right, l); } return l; }
        function getIn(n, l=[]) { if(n){ getIn(n.left, l); l.push(n.val); getIn(n.right, l); } return l; }
        function getPost(n, l=[]) { if(n){ getPost(n.left, l); getPost(n.right, l); l.push(n.val); } return l; }

        // --- จุดสำคัญ: โหลดข้อมูลจาก MariaDB ที่ PHP เตรียมไว้ ---
        const initialData = <?php echo json_encode($db_nodes); ?>;
        initialData.forEach(val => insertLogic(val));
        render();
    </script>
</body>
</html>
