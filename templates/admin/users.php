<?php
/**
 * Admin User List Template
 */
$users = users_get_all();
?>
<div class="container-fluid pt-3">
    <div class="card" data-bs-theme="<?php echo FM_THEME; ?>">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fa fa-users"></i> <?php echo lng('Users') ?></h5>
            <a href="?admin=user_form" class="btn btn-success btn-sm">
                <i class="fa fa-plus-circle"></i> <?php echo lng('New User') ?>
            </a>
        </div>
        <div class="card-body p-0">
            <?php fm_show_message(); ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm mb-0" data-bs-theme="<?php echo FM_THEME; ?>">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?php echo lng('Username') ?></th>
                            <th><?php echo lng('Email') ?></th>
                            <th><?php echo lng('Role') ?></th>
                            <th><?php echo lng('Directories') ?></th>
                            <th><?php echo lng('Actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <?php $dirs = $u['directories'] ? explode("\n", $u['directories']) : []; ?>
                            <tr>
                                <td><?php echo (int)$u['id'] ?></td>
                                <td><?php echo fm_enc($u['username']) ?></td>
                                <td><?php echo fm_enc($u['email']) ?></td>
                                <td>
                                    <span class="badge <?php echo $u['role'] == 'admin' ? 'bg-danger' : 'bg-primary' ?>">
                                        <?php echo lng($u['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['role'] == 'admin'): ?>
                                        <em class="text-muted"><?php echo lng('All Directories') ?></em>
                                    <?php else: ?>
                                        <?php foreach ($dirs as $d): ?>
                                            <span class="badge bg-secondary me-1"><?php echo fm_enc(basename($d)) ?></span>
                                        <?php endforeach; ?>
                                        <?php if (empty($dirs)): ?>
                                            <em class="text-muted"><?php echo lng('None') ?></em>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?admin=user_form&id=<?php echo (int)$u['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fa fa-pencil"></i> <?php echo lng('Edit') ?>
                                    </a>
                                    <?php if ($u['id'] != auth_get_user_id()): ?>
                                        <form method="post" action="?admin=users" class="d-inline" onsubmit="return confirm('<?php echo lng('Are you sure want to') ?> delete?')">
                                            <input type="hidden" name="delete_user" value="<?php echo (int)$u['id'] ?>">
                                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token']); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fa fa-trash"></i> <?php echo lng('Delete') ?>
                                            </button>
                                        </form>
                                        <form method="post" action="?admin=users" class="d-inline">
                                            <input type="hidden" name="reset_user" value="<?php echo (int)$u['id'] ?>">
                                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token']); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning">
                                                <i class="fa fa-envelope"></i> <?php echo lng('Send Reset Link') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="6" class="text-center text-muted"><?php echo lng('No users found') ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
