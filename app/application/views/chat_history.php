<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WAHAHA - <?= $title ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="<?= _assets() ?>/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= _assets() ?>/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="<?= _assets() ?>/plugins/pace/pace.css" rel="stylesheet">
    <link href="<?= _assets() ?>/css/main.min.css" rel="stylesheet">
    <link href="<?= _assets() ?>/css/custom.css" rel="stylesheet">
    <style>
        .chat-container {
            height: 70vh;
            overflow-y: auto;
            background-color: #e5ddd5;
            padding: 20px;
            border-radius: 10px;
        }
        .message {
            margin-bottom: 10px;
            padding: 10px 15px;
            border-radius: 10px;
            max-width: 70%;
            word-wrap: break-word;
        }
        .message.sent {
            background-color: #dcf8c6;
            margin-left: auto;
            text-align: right;
        }
        .message.received {
            background-color: #ffffff;
            margin-right: auto;
        }
        .timestamp {
            font-size: 0.8em;
            color: #999;
            margin-top: 5px;
        }
        .chat-header {
            background-color: #075e54;
            color: white;
            padding: 15px;
            border-radius: 10px 10px 0 0;
        }
    </style>
</head>

<body>
    <div class="app align-content-stretch d-flex flex-wrap">

        <?php require_once(VIEWPATH . '/include_head.php') ?>

        <div class="app-container">
            <div class="app-header">
                <nav class="navbar navbar-light navbar-expand-lg">
                    <div class="container-fluid">
                        <div class="navbar-nav" id="navbarNav">
                            <ul class="navbar-nav">
                                <li class="nav-item">
                                    <a class="nav-link hide-sidebar-toggle-button" href="#"><i class="material-icons">first_page</i></a>
                                </li>
                            </ul>
                        </div>
                        <div class="d-flex">
                            <ul class="navbar-nav">
                            </ul>
                        </div>
                    </div>
                </nav>
            </div>
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container">
                        <div class="row">
                            <div class="col">
                                <div class="page-description p-0">
                                    <h4>Chat History - <?= $receiver ?></h4>
                                </div>
                            </div>
                        </div>
                        <?= _alert() ?>

                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="chat-header">
                                        <h5>Chat with <?= $receiver ?></h5>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="chat-container" id="chatContainer">
                                            <?php foreach ($messages as $msg): ?>
                                                <div class="message <?= $msg->direction ?>">
                                                    <?= htmlspecialchars($msg->message) ?>
                                                    <div class="timestamp">
                                                        <?= date('d/m/Y H:i', strtotime($msg->timestamp)) ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Javascripts -->
    <script src="<?= _assets() ?>/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="<?= _assets() ?>/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="<?= _assets() ?>/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="<?= _assets() ?>/plugins/pace/pace.min.js"></script>
    <script src="<?= _assets() ?>/js/main.min.js"></script>
    <script src="<?= _assets() ?>/js/custom.js"></script>
    <script>
        // Auto scroll to bottom
        document.getElementById('chatContainer').scrollTop = document.getElementById('chatContainer').scrollHeight;
    </script>
</body>

</html>
