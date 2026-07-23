<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 评论邮件通知
 *
 * 当博客收到新评论时，通过 SMTP 自动发送邮件通知博主。
 *
 * @package CommentNotifier
 * @author  Monarchdos
 * @version 1.0.0
 * @link    https://ayfre.com
 */
class CommentNotifier_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 启用插件：挂载到评论完成接口
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget\\Feedback')->finishComment = array('CommentNotifier_Plugin', 'sendMail');

        return _t('评论邮件通知插件已启用，请前往插件设置填写 SMTP 信息');
    }

    /**
     * 禁用插件
     */
    public static function deactivate()
    {
        return _t('评论邮件通知插件已禁用');
    }

    /**
     * 插件配置面板
     *
     * @param Typecho_Widget_Helper_Form $form
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $smtpHost = new Typecho_Widget_Helper_Form_Element_Text(
            'smtpHost',
            NULL,
            'smtp.qq.com',
            _t('SMTP 服务器地址'),
            _t('例如：smtp.qq.com（QQ邮箱）、smtp.163.com（网易）、smtp.gmail.com（Gmail）、smtp.126.com、smtp.exmail.qq.com（企业邮箱）')
        );
        $form->addInput($smtpHost);

        $smtpPort = new Typecho_Widget_Helper_Form_Element_Text(
            'smtpPort',
            NULL,
            '465',
            _t('SMTP 端口'),
            _t('SSL 加密通常为 465，STARTTLS 通常为 587，不加密通常为 25。')
        );
        $form->addInput($smtpPort);

        $smtpSecure = new Typecho_Widget_Helper_Form_Element_Radio(
            'smtpSecure',
            array(
                'ssl'  => _t('SSL（推荐，465 端口）'),
                'tls'  => _t('STARTTLS（587 端口）'),
                'none' => _t('不加密（25 端口，不推荐）')
            ),
            'ssl',
            _t('加密方式'),
            _t('绝大多数邮箱服务商推荐使用 SSL。')
        );
        $form->addInput($smtpSecure);

        $smtpUser = new Typecho_Widget_Helper_Form_Element_Text(
            'smtpUser',
            NULL,
            NULL,
            _t('SMTP 用户名（发件邮箱）'),
            _t('完整邮箱地址，例如 you@qq.com。该邮箱同时作为发件人地址。')
        );
        $form->addInput($smtpUser);

        $smtpPass = new Typecho_Widget_Helper_Form_Element_Text(
            'smtpPass',
            NULL,
            NULL,
            _t('SMTP 密码 / 授权码'),
            _t('QQ、163、126 等邮箱需填写<b>授权码</b>而非登录密码，请在对应邮箱后台开启 SMTP 服务并获取授权码。')
        );
        $form->addInput($smtpPass);

        $fromName = new Typecho_Widget_Helper_Form_Element_Text(
            'fromName',
            NULL,
            NULL,
            _t('发件人名称'),
            _t('收件人看到的发件人名称，留空则使用博客标题。')
        );
        $form->addInput($fromName);

        $toMail = new Typecho_Widget_Helper_Form_Element_Textarea(
            'toMail',
            NULL,
            NULL,
            _t('收件人邮箱（博主）'),
            _t('通知邮件将发送到此处填写的邮箱。<b>每行一个</b>邮箱地址，支持多个收件人。')
        );
        $form->addInput($toMail);

        $excludeSelf = new Typecho_Widget_Helper_Form_Element_Radio(
            'excludeSelf',
            array(
                '1' => _t('是（推荐）'),
                '0' => _t('否')
            ),
            '1',
            _t('排除博主自己的评论'),
            _t('开启后：登录用户发表的评论、或评论者邮箱与发件/收件邮箱相同的评论，均不发送通知。')
        );
        $form->addInput($excludeSelf);

        $waitingOnly = new Typecho_Widget_Helper_Form_Element_Radio(
            'waitingOnly',
            array(
                '0' => _t('全部评论（推荐）'),
                '1' => _t('仅待审核评论')
            ),
            '0',
            _t('通知范围'),
            _t('可选择仅在有评论需要审核时通知，或所有评论都通知。')
        );
        $form->addInput($waitingOnly);

        $mailSubject = new Typecho_Widget_Helper_Form_Element_Text(
            'mailSubject',
            NULL,
            '【{blogName}】{author} 在《{title}》留下了新评论',
            _t('邮件标题模板'),
            _t('可用变量：') . self::placeholdersHelp()
        );
        $form->addInput($mailSubject);

        $defaultBody = <<<HTML
<div style="max-width:600px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f7fa;padding:20px;">
  <div style="background:#fff;border-radius:8px;padding:24px;border:1px solid #e8e8e8;">
    <h2 style="margin-top:0;color:#333;border-bottom:2px solid #1890ff;padding-bottom:10px;">📝 {blogName} 收到新评论</h2>
    <p style="color:#555;font-size:14px;"><b style="color:#1890ff;">{author}</b> 在文章《{title}》中发表了评论：</p>
    <div style="background:#f0f7ff;border-left:3px solid #1890ff;padding:12px 16px;margin:16px 0;color:#333;font-size:14px;line-height:1.7;">{text}</div>
    <table style="width:100%;font-size:13px;color:#666;border-collapse:collapse;">
      <tr><td style="padding:4px 0;width:70px;color:#999;">邮箱</td><td style="padding:4px 0;">{mail}</td></tr>
      <tr><td style="padding:4px 0;color:#999;">网址</td><td style="padding:4px 0;">{url}</td></tr>
      <tr><td style="padding:4px 0;color:#999;">IP</td><td style="padding:4px 0;">{ip}</td></tr>
      <tr><td style="padding:4px 0;color:#999;">时间</td><td style="padding:4px 0;">{time}</td></tr>
      <tr><td style="padding:4px 0;color:#999;">状态</td><td style="padding:4px 0;">{status}</td></tr>
    </table>
    <p style="margin-top:20px;"><a href="{permalink}" style="display:inline-block;background:#1890ff;color:#fff;text-decoration:none;padding:8px 20px;border-radius:4px;font-size:14px;">查看并回复评论</a></p>
  </div>
  <p style="text-align:center;color:#aaa;font-size:12px;margin-top:16px;">此邮件由 {blogName} 自动发送，请勿直接回复</p>
</div>
HTML;

        $mailBody = new Typecho_Widget_Helper_Form_Element_Textarea(
            'mailBody',
            NULL,
            $defaultBody,
            _t('邮件正文模板（HTML）'),
            _t('支持 HTML，可用变量：') . self::placeholdersHelp()
        );
        $mailBody->setAttribute('style', 'min-height:260px;');
        $form->addInput($mailBody);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * finishComment 钩子回调：组装并发送通知邮件
     *
     * @param Widget_Feedback $comment 评论组件实例（已 push 评论数据）
     */
    public static function sendMail($comment)
    {
        try {
            $opts = Helper::options()->plugin('CommentNotifier');
        } catch (\Exception $e) {
            return;
        }

        try {
            $to = trim((string)$opts->toMail);
            if ($to === '') {
                return;
            }

            if ($opts->waitingOnly == '1' && $comment->status === 'approved') {
                return;
            }

            if ($opts->excludeSelf == '1') {
                $user = Typecho_Widget::widget('Widget_User');
                $mailLower = strtolower(trim((string)$comment->mail));
                $sender = strtolower(trim((string)$opts->smtpUser));
                $recipients = self::parseEmails($to);
                $recipientLower = array_map('strtolower', $recipients);
                if ($user->hasLogin()
                    || $mailLower === $sender
                    || in_array($mailLower, $recipientLower)) {
                    return;
                }
            }

            $vars = self::buildVars($comment);
            $subject = self::render(trim((string)$opts->mailSubject), $vars);
            $body = self::render((string)$opts->mailBody, $vars);

            self::smtpSend($to, $subject, $body, $opts);
        } catch (\Exception $e) {
        }
    }

    /**
     * 构造模板变量
     */
    private static function buildVars($comment)
    {
        $options = Helper::options();
        $title = '未知文章';
        $postLink = (string)$comment->permalink;

        try {
            $db = Typecho_Db::get();
            $post = $db->fetchRow(
                $db->select('title', 'cid')
                    ->from('table.contents')
                    ->where('cid = ?', $comment->cid)
                    ->limit(1)
            );
            if ($post) {
                $title = $post['title'];
            }
        } catch (\Exception $e) {
        }

        $commentLink = (string)$comment->permalink;
        if (($pos = strpos($commentLink, '#')) !== false) {
            $postLink = substr($commentLink, 0, $pos);
        } else {
            $postLink = $commentLink;
        }

        $statusMap = array(
            'approved' => '已通过',
            'waiting'  => '待审核',
            'spam'     => '垃圾评论'
        );
        $status = isset($statusMap[$comment->status]) ? $statusMap[$comment->status] : $comment->status;

        return array(
            '{blogName}'  => htmlspecialchars($options->title),
            '{title}'     => htmlspecialchars($title),
            '{author}'    => htmlspecialchars((string)$comment->author),
            '{mail}'      => htmlspecialchars((string)$comment->mail),
            '{url}'       => htmlspecialchars((string)$comment->url),
            '{ip}'        => htmlspecialchars((string)$comment->ip),
            '{text}'      => nl2br(htmlspecialchars((string)$comment->text)),
            '{time}'      => date('Y-m-d H:i:s', $comment->created),
            '{status}'    => $status,
            '{permalink}' => htmlspecialchars($commentLink),
            '{postLink}'  => htmlspecialchars($postLink)
        );
    }

    /**
     * 渲染模板
     */
    private static function render($template, array $vars)
    {
        return strtr($template, $vars);
    }

    /**
     * 解析多个邮箱地址（支持换行、逗号、分号分隔）
     */
    private static function parseEmails($text)
    {
        $text = str_replace(array("\r\n", "\r", "\n", ';'), ',', $text);
        $list = explode(',', $text);
        $result = array();
        foreach ($list as $item) {
            $item = trim($item);
            if ($item !== '' && filter_var($item, FILTER_VALIDATE_EMAIL)) {
                $result[] = $item;
            }
        }
        return $result;
    }

    /**
     * 通过 SMTP 发送邮件（纯 socket 实现）
     */
    private static function smtpSend($toList, $subject, $body, $opts)
    {
        $host    = trim((string)$opts->smtpHost);
        $port    = intval($opts->smtpPort);
        $secure  = (string)$opts->smtpSecure;
        $user    = trim((string)$opts->smtpUser);
        $pass    = (string)$opts->smtpPass;
        $from    = $user;

        if ($host === '' || $user === '' || $pass === '') {
            throw new \Exception('SMTP 配置不完整');
        }

        $recipients = self::parseEmails($toList);
        if (empty($recipients)) {
            throw new \Exception('收件人邮箱为空');
        }

        $ehloDomain = 'localhost';
        $siteHost = parse_url(Helper::options()->siteUrl, PHP_URL_HOST);
        if (!empty($siteHost)) {
            $ehloDomain = $siteHost;
        }

        $context = stream_context_create(array(
            'ssl' => array(
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            )
        ));

        if ($secure === 'ssl') {
            $remote = 'ssl://' . $host . ':' . $port;
        } else {
            $remote = 'tcp://' . $host . ':' . $port;
        }

        $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        if (!$fp) {
            throw new \Exception('连接 SMTP 服务器失败：' . $errstr . ' (' . $errno . ')');
        }
        stream_set_timeout($fp, 15);

        try {
            self::smtpRead($fp, '220');

            self::smtpCmd($fp, 'EHLO ' . $ehloDomain, '250');

            if ($secure === 'tls') {
                self::smtpCmd($fp, 'STARTTLS', '220');
                if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \Exception('STARTTLS 加密升级失败');
                }
                self::smtpCmd($fp, 'EHLO ' . $ehloDomain, '250');
            }

            self::smtpCmd($fp, 'AUTH LOGIN', '334');
            self::smtpCmd($fp, base64_encode($user), '334');
            self::smtpCmd($fp, base64_encode($pass), '235');

            self::smtpCmd($fp, 'MAIL FROM:<' . $from . '>', '250');

            foreach ($recipients as $rcpt) {
                self::smtpCmd($fp, 'RCPT TO:<' . $rcpt . '>', '250');
            }

            self::smtpCmd($fp, 'DATA', '354');

            $fromName = trim((string)$opts->fromName);
            if ($fromName === '') {
                $fromName = Helper::options()->title;
            }

            $headers = array(
                'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
                'To: ' . implode(', ', $recipients),
                'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                'Date: ' . date('r'),
                'Message-ID: <' . md5(uniqid(time(), true)) . '@' . $ehloDomain . '>'
            );

            $msg = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body));
            fwrite($fp, $msg . "\r\n.\r\n");
            self::smtpRead($fp, '250');

            self::smtpCmd($fp, 'QUIT', '221');
        } finally {
            @fclose($fp);
        }

        return true;
    }

    /**
     * 读取 SMTP 响应（支持多行）
     */
    private static function smtpRead($fp, $expect = null)
    {
        $data = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
            if ($line === false) {
                break;
            }
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if ($expect !== null && strpos($data, $expect) !== 0) {
            throw new \Exception('SMTP 响应异常，期望 ' . $expect . '，收到：' . trim($data));
        }

        return $data;
    }

    /**
     * 发送 SMTP 命令并读取响应
     */
    private static function smtpCmd($fp, $cmd, $expect = null)
    {
        fwrite($fp, $cmd . "\r\n");
        return self::smtpRead($fp, $expect);
    }

    /**
     * 模板变量说明
     */
    private static function placeholdersHelp()
    {
        return '<code>{blogName}</code> 博客名称、<code>{title}</code> 文章标题、<code>{author}</code> 评论者、'
            . '<code>{mail}</code> 评论者邮箱、<code>{url}</code> 评论者网址、<code>{ip}</code> 评论者IP、'
            . '<code>{text}</code> 评论内容、<code>{time}</code> 评论时间、<code>{status}</code> 审核状态、'
            . '<code>{permalink}</code> 评论链接、<code>{postLink}</code> 文章链接';
    }
}
