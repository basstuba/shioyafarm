<footer class="footer">
    <div class="footer-inner">
        <div class="footer-info">
            <div class="footer-logo">
                <a class="footer-logo__link" href="{{ route('top') }}">
                    <img class="footer-logo__img" src="{{ asset('image/tool/shioya-farm-logo-small.webp') }}" alt="">
                    <span class="footer-logo__text">しおや農園</span>
                </a>
            </div>
            <div class="footer-farm-info">
                <div class="footer-farm-info-item">
                    <span class="footer-farm-info-item__label">所在地</span>
                    <span class="footer-farm-info-item__value">〒091-2345 北海道架空郡架空町567-34</span>
                </div>
                <div class="footer-farm-info-item">
                    <span class="footer-farm-info-item__label">電話番号</span>
                    <span class="footer-farm-info-item__value">01234-5-6789</span>
                </div>
            </div>
            <div class="footer-copyright">
                <span class="footer-copyright__text">&copy; Shioya Farm</span>
            </div>
        </div>
        <nav class="footer-nav">
            <ul class="footer-nav-list">
                <li class="footer-nav-list-item">
                    <a class="footer-nav-list-item__link" href="{{ route('shipping-fee') }}">送料案内</a>
                </li>
                <li class="footer-nav-list-item">
                    <a class="footer-nav-list-item__link" href="{{ route('terms-of-service') }}">利用規約</a>
                </li>
                <li class="footer-nav-list-item">
                    <a class="footer-nav-list-item__link" href="{{ route('privacy-policy') }}">プライバシーポリシー</a>
                </li>
                <li class="footer-nav-list-item">
                    <a class="footer-nav-list-item__link" href="{{ route('commercial-transaction')}}">特定商取引法に基づく表記</a>
                </li>
            </ul>
        </nav>
    </div>
</footer>