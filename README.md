<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.0-45A?logo=php">
</p>

概要
=

ChatGPTのAPIを使って、カテゴリとタイトルのCSVから記事をマークダウン形式で生成するPHPのスクリプトです。
コンセプトからカテゴリのCSV、カテゴリのCSVから記事タイトルのCSV、記事タイトルのCSVから段落の見出しのCSV、段落の見出しのCSVから本文のMarkDownと段階的にブログ記事を生成していきます。

使い方
=

1. 任意のディレクトリにこのリポジトリをクローンします。

	```command
	git clone https://github.com/synchrovision/openai-article-gen.git
	```

1. [OpenAIのAPI keysのページ](https://platform.openai.com/account/api-keys)でAPIキーを発行し、.env.sampleを複製して.envにリネームし、APIキーを入力します。

	```env:.env
	OPENAI_API_KEY="取得したAPIキー"
	```

1. コンセプトからカテゴリのCSVを生成します。

	```command
	php gen.php -c "任意のコンセプト"
	```

	``categories.csv``というファイル名で``csv``ディレクトリ内に生成されます。  
	生成後はCSVの内容を確認し、適宜修正してください。

	コマンドに``-n 数値``を追加して、生成するカテゴリの個数を指定できます、初期値は5です。

	``categories.csv``には``parent``列に親カテゴリのIDを設定して親子関係を設定することもできます。  
	その場合は次のステップにおいて、子を持つカテゴリの見出しは生成されません。


1. カテゴリのCSVから記事タイトルのCSVを生成します。

	```command
	php gen.php -t
	```

	``titles.csv``というファイル名で``csv``ディレクトリ内に生成されます。  
	生成後はCSVの内容を確認し、適宜修正してください。

	コマンドに``-n 数値``を追加して、生成するタイトルの個数を指定できます、初期値は5で、１カテゴリに5個の記事タイトルが生成されます。

1. 記事タイトルのCSVから見出しのCSVを生成します。

	```command
	php gen.php -h
	```
	``headings.csv``というファイル名で``csv``ディレクトリ内に生成されます。  
	生成後はCSVの内容を確認し、適宜修正してください。

	コマンドに``-n 数値``を追加して、生成する段落の個数を指定できます、初期値は5で、１記事につき５段落の見出しが生成されます。

1. 見出しのCSVから記事のマークダウンを生成します。

	```command
	php gen.php -a
	```

	マークダウンファイルは記事ごとに生成され、``article-[記事ID].md``のファイル名で``md``ディレクトリ内に生成されます。
	生成後は内容を確認し、適宜修正してください。
	コマンドに``-l 数値``を追加して、生成する文章のおよその文字数を指定できます、初期値は800で、１段落の文章は800文字程度で生成されます。
