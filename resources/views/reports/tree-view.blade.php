<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>สรุปโครงสร้างยุทธศาสตร์</title>
    <style>
        /* 💡 สมมติว่าระบบคุณมีการใช้ Font ภาษาไทยอยู่แล้ว เช่น THSarabunNew */
        body {
            font-family: 'THSarabunNew', sans-serif;
            font-size: 16px;
            color: #333;
        }
        h2 { text-align: center; color: #4A148C; margin-bottom: 20px; }

        .tree { list-style-type: none; padding-left: 25px; margin: 0; }
        .tree-root { padding-left: 0; }
        .node { margin-bottom: 8px; padding-left: 10px; border-left: 1px dashed #ccc; }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
            color: #fff;
            font-weight: bold;
            margin-right: 5px;
        }
        .bg-strategy { background-color: #4A148C; }
        .bg-plan { background-color: #F59E0B; }
        .bg-project { background-color: #3B82F6; }
        .bg-task { background-color: #10B981; }

        .text-meta { color: #6b7280; font-size: 14px; margin-left: 5px; }
        .progress { color: #4A148C; font-weight: bold; }
    </style>
</head>
<body>
    <h2>แผนผังโครงสร้างยุทธศาสตร์และโครงการทั้งหมด</h2>

    <ul class="tree tree-root">
        @foreach($strategies as $strategy)
            @include('reports.partials.tree-node', ['node' => $strategy])
        @endforeach
    </ul>
</body>
</html>
