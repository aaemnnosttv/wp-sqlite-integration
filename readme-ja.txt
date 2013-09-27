=== SQLite Integration ===
Contributors: kjmtsh
Plugin Name: SQLite Integration
Plugin URI: http://dogwood.skr.jp/wordpress/sqlite-integration-ja/
Tags: database, SQLite, PDO
Author: Kojima Toshiyasu
Author URI: http://dogwood.skr.jp/
Requires at least: 3.3
Tested up to: 3.6.1
Stable tag: 1.4.1
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

SQLite IntegrationはSQLiteでWordPressを使えるようにするプラグインです。

== Description ==

このプラグインは[SQLite](http://www.sqlite.org/)を使ってWordPressを運用するためのものです。MySQLデータベース・サーバを用意する必要はありません。SQLiteは自己完結的で、サーバを必要とせず、トランザクションをサポートしたデータベース・エンジンです。MySQLのようにたくさんの拡張機能を持たないかわりに、小規模から中規模のトラフィックがあるウェブサイトに適しています。

SQLite Integrationはラッパ・プログラムです。WordPressとSQLiteの間に入って、やり取りを中継し、次のように動作します。

1. WordPressからMySQLに発行されるSQLステートメントをインターセプトします
2. それをSQLiteが実行できる形に書き換えます
3. SQLiteに渡します
4. SQLiteから結果を受け取ります
5. 必要なら、WordPressが望む形式にフォーマットしなおします
6. WordPressに結果を返します

WordPressはMySQLと話していると思っていて、背後で何が起こっているかは知りません。実際にはSQLiteと話しているのですが、WordPressはいつもの通り仕事をします。

SQLite Integrationは[PDO for WordPress](http://wordpress.org/extend/plugins/pdo-for-wordpress)プラグインの後継です。後者は残念なことに、もうメンテナンスされていないようです。SQLite IntegrationはPDO for WordPressの基本的なアイディアと構造を借りて、より多くの機能とユーティリティを追加しました。

= 重要なお知らせ =

このプラグインを使ってWordPress 3.5.x をインストールし、3.6にアップグレードした場合、データベースが意図したとおりに動作していないかもしれません。これに該当する場合は、プラグインを最新版にアップグレードして、管理画面のメンテナンスページに移動し、修復が必要かどうかをチェックしてください。必要なら、そのページで提供しているユーティリティを使って修復をすることができます。

WordPress 3.6 をインストールした場合や、3.5.x を使っている場合は、これに該当しませんので、修復は必要ありません。

= Features =

SQLite Integrationは普通の「プラグイン」ではありません。WordPressをインストールするときに使います。そのため、少し準備が必要です。インストールのセクションを参照してください。[SQLite Integration Page](http://dogwood.skr.jp/wordpress/sqlite-integration/)をご覧になると、もっと詳しい説明を読むことができます。

インストールに成功したら、MySQLを使う他のWordPressと同じように使うことができます。オプションとして、管理パネルでこのプラグインを有効化することができます。有効化すると有益な情報と説明を見ることができます。これは必須ではありませんが、お勧めします。

= Backward Compatibility =

現在[PDO for WordPress](http://wordpress.org/extend/plugins/pdo-for-wordpress)をお使いの場合は、データベースを移行することができます。インストールのセクションをご覧ください。

= Support =

下の方法でコンタクトを取ってください。

1. [Support Forum](http://wordpress.org/support/plugin/sqlite-integration)にポストする。
2. [SQLite Integration(ja)のページ](http://dogwood.skr.jp/wordpress/sqlite-integration-ja/)でメッセージを残す。

注意: WordPress.orgはMySQL以外のデータベースを正式にサポートしていません。だから、WordPress.orgからのサポートは得られません。フォーラムに投稿しても、回答を得ることはまずないでしょう。また、パッチをあてたプラグインを使う場合は、そのプラグインの作者からのサポートはないものと思ってください。自分でリスクを負う必要があります。

= Translation =

ドキュメントは英語で書かれています。日本語のカタログファイルと、.potファイルがアーカイブに含まれています。もしあなたの言語に翻訳をしたら、知らせてください。

== Installation ==

このプラグインは他のプラグインとはちがいます。管理パネルのプラグイン・ページでインストールすることはできません。

まず、WordPressのインストールを準備しなければなりません。Codexの[Installing Wordpress ](http://codex.wordpress.org/Installing_WordPress)をご覧ください。

必要要件をチェックし、WordPressのアーカイブを展開したら、wp-config-sample.phpをwp-config.phpにリネームして、[Codex page](http://codex.wordpress.org/Editing_wp-config.php)に書いてあるように、少し編集する必要があります。データベースの設定*以外*の部分を設定してください。

終わったら、オプションの設定を書き加えることができます。次の説明に従ってください。

* デフォルト(wp-content/database)とは違うディレクトリにSQLiteデータベース・ファイルを置きたい場合は、次の行を追加してください(最後のスラッシュを忘れずに)。
	
	`define('DB_DIR', '/home/youraccount/database_directory/');`
	
	注意: PHPスクリプトがこのディレクトリを作ったり、中にファイルを書き込んだりする権限を持っていることが必要です。
	
* デフォルト(.ht.sqlite)とは違うデータベース・ファイル名を使いたい場合は、次の行を追加してください。
	
	`define('DB_FILE', 'database_file_name');`
	
	注意: PDO for WordPressをお使いの方は、「データベースを移行する」のセクションをご覧ください。

	よくわからない場合は、何も追加する必要はありません。

wp-config.phpの準備が終わったら、次のステップに進みます。

1. プラグインのアーカイブを展開します。

2. アーカイブに含まれるdb.phpファイルをwp-contentディレクトリに移動(またはコピー)してください。

3. sqlite-wordpressディレクトリをwp-content/plugin/ディレクトリの下に移動してください。

  `wordpress/wp-contents/db.php`
	
	と、
	
  `wordpress/wp-contents/sqlite-integration`
	
	のようになります。

さあ、これでお終いです。ディレクトリの構造をそのままに、あなたのサーバにアップロードして、お好きなブラウザでwp-admin/install.phpにアクセスしてください。WordPressのインストールが始まります。Enjoy blogging!

= Migrate your database to SQLite Integration =

一番よい方法は、次のようにすることです。

1. データをエクスポートする。

2. 最新のWordPressを、SQLite Integrationを使って新規インストールする。

3. WordPress Importerを使って以前のデータをインポートする。

何らかの理由で、データがエクスポートできない場合は、次の方法を試すことができます。

1. あなたのMyBlog.sqliteがWordPressの必要とするテーブルを全て含んでいるかどうかチェックしてください。[SQLite Manager Mozilla Addon](https://addons.mozilla.org/en-US/firefox/addon/sqlite-manager/)のようなユーティリティが必要かもしれません。また、Codexの[Database Description](http://codex.wordpress.org/Database_Description)を参照してください。

2. MyBlog.sqliteとdb.phpファイルをバックアップしてください。

3. あなたのMyBlog.sqliteを.ht.sqliteにリネームするか、または、wp-config.phpに次の行を追加してください。
	
	`define('FQDB', 'MyBlog.sqlite');`

4. wp-content/db.phpをSQLite Integrationに含まれている同名のファイルと入れ替えてください。

これでおしまいです。忘れずに必要要件とWordPressのバージョンをチェックしてください。*SQLite IntegrationはWordPress 3.2.x以前のものでは動作しません。*

== Frequently Asked Questions ==

= データベース・ファイルが作られません =

ディレクトリやファイルを作るのに失敗するのは、多くの場合、PHPにその権限がないことが原因です。サーバの設定を確認するか、管理者に聞いてみてください。

= あれこれのプラグインが有効化できません、あるいはちゃんと動作しません =

ある種のプラグイン、特にキャッシュ系のプラグインやデータベース管理系のプラグインはこのプラグインと一緒に使えません。SQLite Integrationを有効化して、ドキュメントの「プラグイン互換性」のセクションをご覧ください。あるいは、[SQLite Integration Plugin Page](http://dogwood.skr.jp/wordpress/plugins/)をご覧ください。

= 管理画面のドキュメントは必要ないのですが =

無効化すればすぐに消えます。有効化と無効化は管理画面の表示・非表示だけで、本体には影響を与えません。プラグインを削除したい場合は、単に削除すれば消えます。

== Screenshots ==

1. システム情報の画面ではデータベースの状態やプラグインの対応状況を見ることができます。

== Requirements ==

* PHP 5.2 以上で PDO extension が必要です(PHP 5.3 以上をお勧めします)。
* PDO SQLite ドライバがロードされている必要があります。

== Known Limitations ==

多くのプラグインはちゃんと動作するはずです。が、中にはそうでないものもあります。一般的には、WordPressの関数を通さず、PHPのMysqlあるいはMysqliライブラリの関数を使ってデータベースを操作しようとするプラグインは問題を起こすでしょう。

他には下のようなものがあります。

= これらのプラグインを使うことはできません。SQLite Integrationと同じファイルを使おうとするからです。 =

* [W3 Total Cache](http://wordpress.org/extend/plugins/w3-total-cache/)
* [DB Cache Reloaded Fix](http://wordpress.org/extend/plugins/db-cache-reloaded-fix/)
* [HyperDB](http://wordpress.org/extend/plugins/hyperdb/)

= これらのプラグインも使えません。SQLiteがエミュレートできないMySQL独自の拡張機能を使っているからです。 =

* [Yet Another Related Posts](http://wordpress.org/extend/plugins/yet-another-related-posts-plugin/)
* [Better Related Posts](http://wordpress.org/extend/plugins/better-related/)

たぶん、もっとあるでしょう。動作しないプラグインを見つけたら、お知らせいただけると助かります。


== Upgrade Notice ==

SQLite Integrationのアップグレードに失敗するようなら、FTPを使っての手動アップグレードを試してみてください。

== Changelog ==

= 1.4.1 (2013-09-27) =
* BETWEEN関数の書き換え方を修正しました。致命的なバグです。新規投稿に'between A and B'というフレーズが含まれていると、公開されず、投稿自体も消えます。
* MP6を使っているときに、管理画面のレイアウトが崩れるのを修正しました。
* 日本語が一部表示されないのを直しました。
* SELECT version()がダミーデータを返すようにしました。
* WP_DEBUGが有効の時に、WordPressのテーブルからカラム情報を読んで表示できるようにしました。

= 1.4 (2013-09-12) =
* アップグレードしたWordPressで期待通り動作しないのを修正するために、データベース管理ユーティリティを追加しました。
* SHOW INDEXクエリにWHERE句がある場合の処理を変更しました。
* ALTER TABLEクエリのバグを修正しました。

= 1.3 (2013-09-04) =
* データベースファイルのスナップショットをzipアーカイブとしてバックアップするユーティリティを追加しました。
* ダッシュボードのスタイルをMP6プラグインに合わせたものに変えました。
* 言語カタログが読み込まれていないときのエラーメッセージの出力方法を一部変更しました。
* query_create.class.phpの_rewrite_field_types()を変更しました。dbDelta()関数が意図したとおりに実行されます。
* BETWEENステートメントが使えるようになりました。
* クエリからインデックスヒントを全て削除して実行するようにしました。
* New StatPressプラグインが使えるように、ALTER TABLE CHANGE COLUMNの扱いを修正しました。
* いくつかの小さなバグを修正しました。

= 1.2.1 (2013-08-04) =
* wp-db.phpの変更にともなって、wpdb::real_escapeプロパティを削除しました。WordPress 3.6 との互換性を保つための変更です。

= 1.2 (2013-08-03) =
* カレンダー・ウィジェットでの不具合に対応するため、日付フォーマットとそのクオートを修正しました。
* Windows マシンでパッチファイルが削除できなかったのを修正しました。
* パッチファイルをアップロードするときに textdomain のエラーが出るのを修正しました。
* ON DUPLICATE KEY UPDATEをともなったクエリの処理を変更しました。
* readme.txt と readme-ja.txt の間違いを直しました。

= 1.1 (2013-07-24) =
* DROP INDEX 単独のクエリが動作していなかったのを修正しました。
* shutdown_hook で destruct() を実行していたのをやめました。
* LOCATE() 関数を使えるようにしました。

= 1.0 (2013-07-07) =
* 最初のリリース。
