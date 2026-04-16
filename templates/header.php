<!DOCTYPE html>
<html data-bs-theme="<?php echo FM_THEME; ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">
    <meta name="googlebot" content="noindex">
    <?php if ($favicon_path):
        echo '<link rel="icon" href="' . fm_enc($favicon_path) . '" type="image/png">'; endif; ?>
    <title><?php echo fm_enc(APP_TITLE) ?> |
        <?php echo isset($_GET['view']) ? $_GET['view'] : (isset($_GET['edit']) ? $_GET['edit'] : 'Files'); ?></title>
    <?php print_external('pre-jsdelivr'); ?>
    <?php print_external('pre-cloudflare'); ?>
    <?php print_external('css-bootstrap'); ?>
    <?php print_external('css-font-awesome'); ?>
    <?php if (FM_USE_HIGHLIGHTJS && isset($_GET['view'])): ?>
        <?php print_external('css-highlightjs'); ?>
    <?php endif; ?>
    <script type="text/javascript">
        window.csrf = '<?php echo $_SESSION['token']; ?>';
    </script>
    <style>
        html {
            -moz-osx-font-smoothing: grayscale;
            -webkit-font-smoothing: antialiased;
            height: 100%;
            scroll-behavior: smooth;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            font-size: 15px;
            color: #222;
            background: #F7F7F7;
        }

        body.navbar-fixed {
            margin-top: 55px;
        }

        a,
        a:hover,
        a:visited,
        a:focus {
            text-decoration: none !important;
        }

        .filename,
        td,
        th {
            white-space: nowrap;
        }

        .navbar-brand {
            font-weight: bold;
        }

        .nav-item.avatar a {
            cursor: pointer;
            text-transform: capitalize;
        }

        .nav-item.avatar a>i {
            font-size: 15px;
        }

        .nav-item.avatar .dropdown-menu a {
            font-size: 13px;
        }

        #search-addon {
            font-size: 12px;
            border-right-width: 0;
        }

        .brl-0 {
            background: transparent;
            border-left: 0;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .brr-0 {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .bread-crumb {
            color: #cccccc;
            font-style: normal;
        }

        #main-table .filename a {
            color: #222222;
        }

        .table td,
        .table th {
            vertical-align: middle !important;
        }

        .table-sm td,
        .table-sm th {
            padding: .4rem;
        }

        .table-bordered td,
        .table-bordered th {
            border: 1px solid #f1f1f1;
        }

        .hidden {
            display: none;
        }

        pre.with-hljs {
            padding: 0;
            overflow: hidden;
        }

        pre.with-hljs code {
            margin: 0;
            border: 0;
            overflow: scroll;
        }

        code.maxheight,
        pre.maxheight {
            max-height: 512px;
        }

        .fa.fa-home {
            font-size: 1.3em;
            vertical-align: bottom;
        }

        form.dropzone {
            min-height: 200px;
            border: 2px dashed #007bff;
            line-height: 6rem;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .message {
            padding: 4px 7px;
            border: 1px solid #ddd;
            background-color: #fff;
        }

        .message.ok {
            border-color: green;
            color: green;
        }

        .message.error {
            border-color: red;
            color: red;
        }

        .message.alert {
            border-color: orange;
            color: orange;
        }

        .preview-img {
            max-width: 100%;
            max-height: 80vh;
            cursor: zoom-in;
        }

        input#preview-img-zoomCheck[type=checkbox] {
            display: none;
        }

        input#preview-img-zoomCheck[type=checkbox]:checked~label>img {
            max-width: none;
            max-height: none;
            cursor: zoom-out;
        }

        .inline-actions>a>i {
            font-size: 1em;
            margin-left: 5px;
            background: #3785c1;
            color: #fff;
            padding: 3px 4px;
            border-radius: 3px;
        }

        .preview-video {
            position: relative;
            max-width: 100%;
            height: 0;
            padding-bottom: 62.5%;
            margin-bottom: 10px;
        }

        .preview-video video {
            position: absolute;
            width: 100%;
            height: 100%;
            left: 0;
            top: 0;
            background: #000;
        }

        .compact-table {
            border: 0;
            width: auto;
        }

        .compact-table td,
        .compact-table th {
            width: 100px;
            border: 0;
            text-align: center;
        }

        .filename {
            max-width: 420px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .break-word {
            word-wrap: break-word;
            margin-left: 30px;
        }

        #editor {
            position: absolute;
            right: 15px;
            top: 100px;
            bottom: 15px;
            left: 15px;
        }

        #normal-editor {
            border-radius: 3px;
            border-width: 2px;
            padding: 10px;
            outline: none;
        }

        .btn-2 {
            padding: 4px 10px;
            font-size: small;
        }

        i.fa.fa-folder-o {
            color: #0157b3;
        }

        i.fa.fa-picture-o {
            color: #26b99a;
        }

        i.fa.fa-file-archive-o {
            color: #da7d7d;
        }

        .btn-2 i.fa.fa-file-archive-o {
            color: inherit;
        }

        i.fa.fa-file-text-o {
            color: #0096e6;
        }

        i.fa.fa-file-code-o {
            color: #007bff;
        }

        i.go-back {
            font-size: 1.2em;
            color: #007bff;
        }

        .main-nav {
            padding: 0.2rem 1rem;
            box-shadow: 0 4px 5px 0 rgba(0, 0, 0, .14), 0 1px 10px 0 rgba(0, 0, 0, .12), 0 2px 4px -1px rgba(0, 0, 0, .2);
        }

        .dataTables_filter {
            display: none;
        }

        .footer-action li {
            margin-bottom: 10px;
        }

        .app-v-title {
            font-size: 24px;
            font-weight: 300;
            letter-spacing: -.5px;
            text-transform: uppercase;
        }

        #snackbar {
            visibility: hidden;
            min-width: 250px;
            margin-left: -125px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 2px;
            padding: 16px;
            position: fixed;
            z-index: 1;
            left: 50%;
            bottom: 30px;
            font-size: 17px;
        }

        #snackbar.show {
            visibility: visible;
            -webkit-animation: fadein .5s, fadeout .5s 2.5s;
            animation: fadein .5s, fadeout .5s 2.5s;
        }

        @keyframes fadein {
            from {
                bottom: 0;
                opacity: 0;
            }

            to {
                bottom: 30px;
                opacity: 1;
            }
        }

        @keyframes fadeout {
            from {
                bottom: 30px;
                opacity: 1;
            }

            to {
                bottom: 0;
                opacity: 0;
            }
        }

        .table-hover>tbody>tr:hover>td:first-child {
            border-left: 1px solid #1b77fd;
        }

        .filename>a>i {
            margin-right: 3px;
        }

        .fs-7 {
            font-size: 14px;
        }

        ul#search-wrapper {
            padding-left: 0;
            border: 1px solid #ecececcc;
        }

        ul#search-wrapper li {
            list-style: none;
            padding: 5px;
            border-bottom: 1px solid #ecececcc;
        }

        ul#search-wrapper li:nth-child(odd) {
            background: #f9f9f9cc;
        }

        .c-preview-img {
            max-width: 300px;
        }

        .float-right {
            float: right;
        }

        .lds-facebook {
            display: none;
            position: relative;
            width: 64px;
            height: 64px;
        }

        .lds-facebook div,
        .lds-facebook.show-me {
            display: inline-block;
        }

        .lds-facebook div {
            position: absolute;
            left: 6px;
            width: 13px;
            background: #007bff;
            animation: lds-facebook 1.2s cubic-bezier(0, .5, .5, 1) infinite;
        }

        .lds-facebook div:nth-child(1) {
            left: 6px;
            animation-delay: -.24s;
        }

        .lds-facebook div:nth-child(2) {
            left: 26px;
            animation-delay: -.12s;
        }

        .lds-facebook div:nth-child(3) {
            left: 45px;
            animation-delay: 0s;
        }

        @keyframes lds-facebook {
            0% {
                top: 6px;
                height: 51px;
            }

            100%,
            50% {
                top: 19px;
                height: 26px;
            }
        }

        table.dataTable thead .sorting {
            cursor: pointer;
            background-repeat: no-repeat;
            background-position: center right;
        }

        .bread-crumb i {
            color: #cccccc;
            font-style: normal;
        }
    </style>
    <?php if (FM_THEME == 'dark'): ?>
        <style>
            body.theme-dark {
                background-image: linear-gradient(90deg, #1c2429, #263238);
                color: #CFD8DC;
            }

            .list-group .list-group-item {
                background: #343a40;
            }

            .theme-dark .navbar-nav i,
            .navbar-nav .dropdown-toggle,
            .break-word {
                color: #CFD8DC;
            }

            a,
            a:hover,
            a:visited,
            a:active,
            #main-table .filename a,
            i.fa.fa-folder-o,
            i.go-back {
                color: #f3daa6;
            }

            ul#search-wrapper li:nth-child(odd) {
                background: #212a2f;
            }

            .theme-dark .btn-outline-primary {
                color: #b8e59c;
                border-color: #b8e59c;
            }

            .theme-dark input.form-control {
                background-color: #101518;
                color: #CFD8DC;
            }

            .theme-dark .dropzone {
                background: transparent;
            }

            .theme-dark .inline-actions>a>i {
                background: #79755e;
            }

            .message {
                background-color: #212529;
            }

            form.dropzone {
                border-color: #79755e;
            }
        </style>
    <?php endif; ?>
</head>

<body
    class="<?php echo (FM_THEME == 'dark') ? 'theme-dark' : ''; ?> <?php echo $sticky_navbar ? 'navbar-fixed' : 'navbar-normal'; ?>">
    <div id="wrapper" class="container-fluid">

        <!-- New Item creation Modal -->
        <div class="modal fade" id="createNewItem" tabindex="-1" role="dialog" data-bs-backdrop="static"
            data-bs-keyboard="false" aria-labelledby="newItemModalLabel" aria-hidden="true"
            data-bs-theme="<?php echo FM_THEME; ?>">
            <div class="modal-dialog" role="document">
                <form class="modal-content" method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="newItemModalLabel"><i
                                class="fa fa-plus-square fa-fw"></i><?php echo lng('CreateNewItem') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p><label for="newfile"><?php echo lng('ItemType') ?></label></p>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" id="customRadioInline1" name="newfile"
                                value="file">
                            <label class="form-check-label" for="customRadioInline1"><?php echo lng('File') ?></label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" id="customRadioInline2" name="newfile"
                                value="folder" checked>
                            <label class="form-check-label" for="customRadioInline2"><?php echo lng('Folder') ?></label>
                        </div>
                        <p class="mt-3"><label for="newfilename"><?php echo lng('ItemName') ?></label></p>
                        <input type="text" name="newfilename" id="newfilename" value="" class="form-control"
                            placeholder="<?php echo lng('Enter here...') ?>" required>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                        <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal"><i
                                class="fa fa-times-circle"></i> <?php echo lng('Cancel') ?></button>
                        <button type="submit" class="btn btn-success"><i class="fa fa-check-circle"></i>
                            <?php echo lng('CreateNow') ?></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Advanced Search Modal -->
        <div class="modal fade" id="searchModal" tabindex="-1" role="dialog" aria-labelledby="searchModalLabel"
            aria-hidden="true" data-bs-theme="<?php echo FM_THEME; ?>">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title col-10" id="searchModalLabel">
                            <div class="input-group mb-3">
                                <input type="text" class="form-control"
                                    placeholder="<?php echo lng('Search') ?> <?php echo lng('a files') ?>"
                                    aria-label="<?php echo lng('Search') ?>" aria-describedby="search-addon3"
                                    id="advanced-search" autofocus required>
                                <span class="input-group-text" id="search-addon3"><i class="fa fa-search"></i></span>
                            </div>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="lds-facebook">
                            <div></div>
                            <div></div>
                            <div></div>
                        </div>
                        <ul id="search-wrapper">
                            <p class="m-2"><?php echo lng('Search file in folder and subfolders...') ?></p>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rename Modal -->
        <div class="modal modal-alert" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog"
            id="renameDailog" data-bs-theme="<?php echo FM_THEME; ?>">
            <div class="modal-dialog" role="document">
                <form class="modal-content rounded-3 shadow" method="post" autocomplete="off">
                    <div class="modal-body p-4 text-center">
                        <h5 class="mb-3"><?php echo lng('Are you sure want to rename?') ?></h5>
                        <p class="mb-1">
                            <input type="text" name="rename_to" id="js-rename-to" class="form-control"
                                placeholder="<?php echo lng('Enter new file name') ?>" required>
                            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                            <input type="hidden" name="rename_from" id="js-rename-from">
                        </p>
                    </div>
                    <div class="modal-footer flex-nowrap p-0">
                        <button type="button"
                            class="btn btn-lg btn-link fs-6 text-decoration-none col-6 m-0 rounded-0 border-end"
                            data-bs-dismiss="modal"><?php echo lng('Cancel') ?></button>
                        <button type="submit"
                            class="btn btn-lg btn-link fs-6 text-decoration-none col-6 m-0 rounded-0"><strong><?php echo lng('Okay') ?></strong></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Confirm Modal Template -->
        <script type="text/html" id="js-tpl-confirm">
    <div class="modal modal-alert confirmDailog" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" id="confirmDailog-<%this.id%>" data-bs-theme="<?php echo FM_THEME; ?>">
        <div class="modal-dialog" role="document">
            <form class="modal-content rounded-3 shadow" method="post" autocomplete="off" action="<%this.action%>">
                <div class="modal-body p-4 text-center">
                    <h5 class="mb-2"><?php echo lng('Are you sure want to') ?> <%this.title%> ?</h5>
                    <p class="mb-1"><%this.content%></p>
                </div>
                <div class="modal-footer flex-nowrap p-0">
                    <button type="button" class="btn btn-lg btn-link fs-6 text-decoration-none col-6 m-0 rounded-0 border-end" data-bs-dismiss="modal"><?php echo lng('Cancel') ?></button>
                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                    <button type="submit" class="btn btn-lg btn-link fs-6 text-decoration-none col-6 m-0 rounded-0" data-bs-dismiss="modal"><strong><?php echo lng('Okay') ?></strong></button>
                </div>
            </form>
        </div>
    </div>
</script>