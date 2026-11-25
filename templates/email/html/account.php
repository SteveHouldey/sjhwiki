<?php
$this->assign('title', h($subject));
?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f7fa;">
    <tr>
        <td align="center" style="padding:32px 16px;font-family:Arial,Helvetica,sans-serif;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;">
                <tr>
                    <td style="padding:20px 24px;background:#1976d2;color:#ffffff;border-radius:7px 7px 0 0;">
                        <span style="display:inline-block;width:28px;height:28px;line-height:28px;text-align:center;border:2px solid #ffffff;border-radius:6px;font-weight:bold;">W</span>
                        <span style="padding-left:8px;font-size:20px;font-weight:bold;">Wiki</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px 24px;background:#ffffff;color:#263445;line-height:1.6;">
                        <h1 style="margin:0 0 16px;font-size:24px;line-height:1.3;color:#233346;"><?= h($subject) ?></h1>
                        <p style="margin:0 0 24px;"><?= h($message) ?></p>
                        <p style="margin:0 0 24px;">
                            <a href="<?= h($link) ?>" style="display:inline-block;padding:12px 20px;border-radius:5px;background:#1976d2;color:#ffffff;font-weight:bold;text-decoration:none;"><?= h($action) ?></a>
                        </p>
                        <p style="margin:0 0 16px;color:#526476;">This link is valid for <?= h($expiry) ?> and can be used once.</p>
                        <p style="margin:0 0 8px;color:#6b7b8e;font-size:13px;">If the button does not work, copy and paste this link into your browser:</p>
                        <p style="margin:0 0 24px;font-size:13px;word-break:break-all;"><a href="<?= h($link) ?>" style="color:#155da8;"><?= h($link) ?></a></p>
                        <p style="margin:0;color:#6b7b8e;font-size:13px;">If you did not request this, you can ignore this email.</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 24px;background:#ffffff;border-top:1px solid #e2e8ef;border-radius:0 0 7px 7px;color:#6b7b8e;font-size:13px;">
                        &copy; <?= h(gmdate('Y')) ?> Steven Houldey
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
