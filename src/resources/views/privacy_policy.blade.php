@extends('layouts.app')

@section('body-class', 'legal-page')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/privacy-policy.css') }}">
@endsection

@section('content')
    <div class="privacy-policy">
        <div class="container">
            <h2 class="title">プライバシーポリシー</h2>
            <section class="section">
                <p class="section-text">
                    しおや農園（以下，「当社」といいます。）は，本ウェブサイト上で提供するサービス（以下,「本サービス」といいます。）における，ユーザーの個人情報の取扱いについて，以下のとおりプライバシーポリシー（以下，「本ポリシー」といいます。）を定めます。
                </p>
            </section>
            <section class="section">
                <h3 class="section-title">第1条（個人情報）</h3>
                <p class="section-text">
                    「個人情報」とは，個人情報保護法にいう「個人情報」を指すものとし，生存する個人に関する情報であって，当該情報に含まれる氏名，生年月日，住所，電話番号，連絡先その他の記述等により特定の個人を識別できる情報及び容貌，指紋，声紋にかかるデータ，及び健康保険証の保険者番号などの当該情報単体から特定の個人を識別できる情報（個人識別情報）を指します。
                </p>
            </section>
            <section class="section">
                <h3 class="section-title">第2条（個人情報の収集方法）</h3>
                <p class="section-text">
                    当社は，ユーザーが利用登録をする際に氏名，生年月日，住所，電話番号，メールアドレス，銀行口座番号，クレジットカード番号，運転免許証番号などの個人情報をお尋ねすることがあります。また，ユーザーと提携先などとの間でなされたユーザーの個人情報を含む取引記録や決済に関する情報を,当社の提携先（情報提供元，広告主，広告配信先などを含みます。以下，｢提携先｣といいます。）などから収集することがあります。
                </p>
            </section>
            <section class="section">
                <h3 class="section-title">第3条（個人情報を収集・利用する目的）</h3>
                <p class="section-text">
                    当社が個人情報を収集・利用する目的は，以下のとおりです。
                </p>
                <ol>
                    <li>
                        <p class="section-text">
                            当社サービスの提供・運営のため
                        </p>
                    </li>
                    <li>
                        <p class="section-text">
                            ユーザーからのお問い合わせに回答するため（本人確認を行うことを含む）
                        </p>
                    </li>
                    <li>
                        <p class="section-text">
                            ユーザーが利用中のサービスの新機能，更新情報，キャンペーン等及び当社が提供する他のサービスの案内のメールを送付するため
                        </p>
                    </li>
                    <li>
                        <p class="section-text">
                            メンテナンス，重要なお知らせなど必要に応じたご連絡のため
                        </p>
                    </li>
                    <li>
                        <p class="section-text">
                            利用規約に違反したユーザーや，不正・不当な目的でサービスを利用しようとするユーザーの特定をし，ご利用をお断りするため
                        </p>
                    </li>
                    <li>
                        <p class="section-text">
                            ユーザーにご自身の登録情報の閲覧や変更，削除，ご利用状況の閲覧を行っていただくため
                        </p>
                    </li>
                    <li>
                        <p class="section-text">
                            上記の利用目的に付随する目的
                        </p>
                    </li>
                </ol>
            </section>
            <section class="section">
                <h3 class="section-title">第4条（利用目的の変更）</h3>
                <ol>
                    <li>
                        <p class="section-text">
                            当社は，利用目的が変更前と関連性を有すると合理的に認められる場合に限り，個人情報の利用目的を変更するものとします。
                        </p>
                    </li>
                    <li>
                        <p class="section-text">
                            利用目的の変更を行った場合には，変更後の目的について，当社所定の方法により，ユーザーに通知し，または本ウェブサイト上に公表するものとします。
                        </p>
                    </li>
                </ol>
            </section>
            <section class="section">
                <h3 class="section-title">第5条（個人情報の第三者提供）</h3>
                <ol>
                    <li>
                        <p class="section-text">
                            当社は，次に掲げる場合を除いて，あらかじめユーザーの同意を得ることなく，第三者に個人情報を提供することはありません。ただし，個人情報保護法その他の法令で認められる場合を除きます。
                        </p>
                    </li>
                    <li>
                        <p class="section-text">
                            人の生命，身体または財産の保護のために必要がある場合であって，本人の同意を得ることが困難であるとき
                        </p>
                    </li>
                    <li>
                        <p class="section-text">
                            公衆衛生の向上または児童の健全な育成の推進のために特に必要がある場合であって，本人の同意を得ることが困難であるとき
                        </p>
                    </li>
                    <li>
                        <p class="section-text">
                            国の機関もしくは地方公共団体またはその委託を受けた者が法令の定める事務を遂行することに対して協力する必要がある場合であって，本人の同意を得ることにより当該事務の遂行に支障を及ぼすおそれがあるとき
                        </p>
                    </li>
                    <li>
                        <p class="section-text">
                            予め次の事項を告知あるいは公表し，かつ当社が個人情報保護委員会に届出をしたとき
                        </p>
                        <ol type="a">
                            <li>
                                <p class="section-text">
                                    利用目的に第三者への提供を含むこと
                                </p>
                            </li>
                            <li>
                                <p class="section-text">
                                    第三者に提供されるデータの項目
                                </p>
                            </li>
                            <li>
                                <p class="section-text">
                                    第三者への提供の手段または方法
                                </p>
                            </li>
                            <li>
                                <p class="section-text">
                                    本人の求めに応じて個人情報の第三者への提供を停止すること
                                </p>
                            </li>
                            <li>
                                <p class="section-text">
                                    本人の求めを受け付ける方法
                                </p>
                            </li>
                        </ol>
                    </li>
                    <li>
                        <p class="section-text">
                            前項の定めにかかわらず，次に掲げる場合には，当該情報の提供先は第三者に該当しないものとします。
                        </p>
                    </li>
                    <li>
                        <p class="section-text">
                            当社が利用目的の達成に必要な範囲内において個人情報の取扱いの全部または一部を委託する場合
                        </p>
                    </li>
                    <li>
                        <p class="section-text">
                            合併その他の事由による事業の承継に伴って個人情報が提供される場合
                        </p>
                    </li>
                    <li>
                        <p class="section-text">
                            個人情報を特定の者との間で共同して利用する場合であって，その旨並びに共同して利用される個人情報の項目，共同して利用する者の範囲，利用する者の利用目的および当該個人情報の管理について責任を有する者の氏名または名称について，あらかじめ本人に通知し，または本人が容易に知り得る状態に置いた場合
                        </p>
                    </li>
                </ol>
            </section>
            <section class="section"></section>
            <section class="section"></section>
            <section class="section"></section>
            <section class="section"></section>
            <section class="section"></section>
        </div>
    </div>
@endsection
