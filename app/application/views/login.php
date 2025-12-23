<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LOGIN BRAY</title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="<?= _assets() ?>/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= _assets() ?>/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="<?= _assets() ?>/plugins/pace/pace.css" rel="stylesheet">
    <link href="<?= _assets() ?>/css/main.min.css" rel="stylesheet">
    <link href="<?= _assets() ?>/css/custom.css" rel="stylesheet">
</head>

<body>
    <div class="app app-auth-sign-in align-content-stretch d-flex flex-wrap justify-content-end">
        <div class="app-auth-background">

        </div>
        <div class="app-auth-container">
            <div class="logo">
                <a style="background: url(<?= _assets('images/velixs.png') ?>) no-repeat; height: 60px" href="#">WAHAHA</a>
            </div>
            <p class="auth-description">Please sign-in to your account and continue to the dashboard.</p>
            <?= _alert() ?>
            <form action="<?= base_url('auth/login') ?>" method="post">
                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>" />
                <div class="auth-credentials m-b-xxl">
                    <label for="signInEmail" class="form-label">Username</label>
                    <input type="text" name="username" class="form-control m-b-md" id="signInEmail" aria-describedby="signInEmail" placeholder="Enter Username">

                    <label for="signInPassword" class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" id="signInPassword" aria-describedby="signInPassword" placeholder="&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;">
                    <br>
                    <input class="form-check-input" name="remember" type="checkbox" checked>
                    <label class="form-check-label" for="flexCheckDefault">
                        Remember Me
                    </label>
                </div>

                <!-- Consent Section -->
                <div class="auth-consent m-b-xxl">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="accept_terms" id="acceptTerms" required>
                        <label class="form-check-label" for="acceptTerms">
                            I agree to the <a href="<?= base_url('terms-of-service') ?>" target="_blank">Terms of Service</a>
                        </label>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="accept_privacy" id="acceptPrivacy" required>
                        <label class="form-check-label" for="acceptPrivacy">
                            I agree to the <a href="<?= base_url('privacy-policy') ?>" target="_blank">Privacy Policy</a>
                        </label>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="accept_data_processing" id="acceptDataProcessing" required>
                        <label class="form-check-label" for="acceptDataProcessing">
                            I consent to the processing of my data for WhatsApp Business messaging services
                        </label>
                    </div>
                </div>

                <div class="auth-submit">
                    <button type="submit" class="btn btn-primary">Login</button>
                </div>
            </form>
        </div>
    </div>


    <script src="<?= _assets() ?>/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="<?= _assets() ?>/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="<?= _assets() ?>/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="<?= _assets() ?>/plugins/pace/pace.min.js"></script>
    <script src="<?= _assets() ?>/js/main.min.js"></script>
    <script src="<?= _assets() ?>/js/custom.js"></script>
    <script>
        <?php if ($this->session->flashdata('error')): ?>
            alert('<?= $this->session->flashdata('error') ?>');
        <?php endif; ?>
    </script>
</body>

</html>