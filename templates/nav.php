<?php
/**
 * Navigation bar template
 * @var string $path Current FM_PATH
 */
$isStickyNavBar = $sticky_navbar ? 'fixed-top' : '';
?>
<nav class="navbar navbar-expand-lg mb-4 main-nav <?php echo $isStickyNavBar ?> bg-body-tertiary"
    data-bs-theme="<?php echo FM_THEME; ?>">
    <a class="navbar-brand"><?php echo lng('AppTitle') ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
        aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <?php
        $clean_path = fm_clean_path(FM_PATH);
        $root_url_link = "<a href='?p='><i class='fa fa-home' aria-hidden='true' title='" . FM_ROOT_PATH . "'></i></a>";
        $sep = '<i class="bread-crumb"> / </i>';
        if ($clean_path != '') {
            $exploded = explode('/', $clean_path);
            $count = count($exploded);
            $array = [];
            $parent = '';
            for ($i = 0; $i < $count; $i++) {
                $parent = trim($parent . '/' . $exploded[$i], '/');
                $parent_enc = urlencode($parent);
                $array[] = "<a href='?p={$parent_enc}'>" . fm_enc(fm_convert_win($exploded[$i])) . "</a>";
            }
            $root_url_link .= $sep . implode($sep, $array);
        }
        echo '<div class="col-xs-6 col-sm-5">' . $root_url_link . (isset($editFile) ? $editFile : '') . '</div>';
        ?>

        <div class="col-xs-6 col-sm-7">
            <ul class="navbar-nav justify-content-end" data-bs-theme="<?php echo FM_THEME; ?>">
                <li class="nav-item mr-2">
                    <div class="input-group input-group-sm mr-1" style="margin-top:4px;">
                        <input type="text" class="form-control" placeholder="<?php echo lng('Search') ?>"
                            aria-label="<?php echo lng('Search') ?>" id="search-addon">
                        <div class="input-group-append">
                            <span class="input-group-text brl-0 brr-0" id="search-addon2"><i
                                    class="fa fa-search"></i></span>
                        </div>
                        <div class="input-group-append btn-group">
                            <span class="input-group-text dropdown-toggle brl-0" data-bs-toggle="dropdown"
                                aria-haspopup="true" aria-expanded="false"></span>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="<?php echo $clean_path ? $clean_path : '.'; ?>"
                                    id="js-search-modal" data-bs-toggle="modal" data-bs-target="#searchModal">
                                    <?php echo lng('Advanced Search') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </li>

                <?php if (!FM_READONLY): ?>
                    <li class="nav-item">
                        <a title="<?php echo lng('Upload') ?>" class="nav-link"
                            href="?p=<?php echo urlencode(FM_PATH) ?>&amp;upload">
                            <i class="fa fa-cloud-upload" aria-hidden="true"></i> <?php echo lng('Upload') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a title="<?php echo lng('NewItem') ?>" class="nav-link" href="#createNewItem"
                            data-bs-toggle="modal" data-bs-target="#createNewItem">
                            <i class="fa fa-plus-square"></i> <?php echo lng('NewItem') ?>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Client directory switcher -->
                <?php if (!auth_is_admin()): ?>
                    <?php
                    $dirs = isset($_SESSION[FM_SESSION_ID]['directories']) ? $_SESSION[FM_SESSION_ID]['directories'] : [];
                    $active_dir_idx = isset($_SESSION[FM_SESSION_ID]['active_dir']) ? $_SESSION[FM_SESSION_ID]['active_dir'] : 0;
                    if (count($dirs) > 1):
                        ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" id="dirSwitcher" data-bs-toggle="dropdown"
                                aria-expanded="false">
                                <i class="fa fa-folder-o"></i> <?php echo lng('Switch Directory') ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" data-bs-theme="<?php echo FM_THEME; ?>">
                                <?php foreach ($dirs as $i => $d): ?>
                                    <a class="dropdown-item <?php echo ($i == $active_dir_idx) ? 'active' : ''; ?>"
                                        href="?switch_dir=<?php echo $i ?>">
                                        <?php echo fm_enc(basename($d)) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Admin menu -->
                <?php if (auth_is_admin()): ?>
                    <li class="nav-item">
                        <a title="<?php echo lng('Users') ?>" class="nav-link" href="?admin=users">
                            <i class="fa fa-users" aria-hidden="true"></i> <?php echo lng('Users') ?>
                        </a>
                    </li>
                <?php endif; ?>

                <li class="nav-item avatar dropdown">
                    <a class="nav-link dropdown-toggle" id="navbarDropdownMenuLink-5" data-bs-toggle="dropdown"
                        aria-expanded="false">
                        <i class="fa fa-user-circle"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end text-small shadow"
                        aria-labelledby="navbarDropdownMenuLink-5" data-bs-theme="<?php echo FM_THEME; ?>">
                        <span class="dropdown-item-text text-muted small"><?php echo fm_enc(auth_get_username()) ?>
                            (<?php echo lng(auth_get_role()) ?>)</span>
                        <div class="dropdown-divider"></div>
                        <?php if (!FM_READONLY): ?>
                            <a title="<?php echo lng('Settings') ?>" class="dropdown-item nav-link"
                                href="?p=<?php echo urlencode(FM_PATH) ?>&amp;settings=1">
                                <i class="fa fa-cog" aria-hidden="true"></i> <?php echo lng('Settings') ?>
                            </a>
                        <?php endif ?>
                        <a title="<?php echo lng('Help') ?>" class="dropdown-item nav-link"
                            href="?p=<?php echo urlencode(FM_PATH) ?>&amp;help=2">
                            <i class="fa fa-exclamation-circle" aria-hidden="true"></i> <?php echo lng('Help') ?>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a title="<?php echo lng('Logout') ?>" class="dropdown-item nav-link" href="?logout=1">
                            <i class="fa fa-sign-out" aria-hidden="true"></i> <?php echo lng('Logout') ?>
                        </a>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>