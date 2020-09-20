var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
var __generator = (this && this.__generator) || function (thisArg, body) {
    var _ = { label: 0, sent: function() { if (t[0] & 1) throw t[1]; return t[1]; }, trys: [], ops: [] }, f, y, t, g;
    return g = { next: verb(0), "throw": verb(1), "return": verb(2) }, typeof Symbol === "function" && (g[Symbol.iterator] = function() { return this; }), g;
    function verb(n) { return function (v) { return step([n, v]); }; }
    function step(op) {
        if (f) throw new TypeError("Generator is already executing.");
        while (_) try {
            if (f = 1, y && (t = op[0] & 2 ? y["return"] : op[0] ? y["throw"] || ((t = y["return"]) && t.call(y), 0) : y.next) && !(t = t.call(y, op[1])).done) return t;
            if (y = 0, t) op = [op[0] & 2, t.value];
            switch (op[0]) {
                case 0: case 1: t = op; break;
                case 4: _.label++; return { value: op[1], done: false };
                case 5: _.label++; y = op[1]; op = [0]; continue;
                case 7: op = _.ops.pop(); _.trys.pop(); continue;
                default:
                    if (!(t = _.trys, t = t.length > 0 && t[t.length - 1]) && (op[0] === 6 || op[0] === 2)) { _ = 0; continue; }
                    if (op[0] === 3 && (!t || (op[1] > t[0] && op[1] < t[3]))) { _.label = op[1]; break; }
                    if (op[0] === 6 && _.label < t[1]) { _.label = t[1]; t = op; break; }
                    if (t && _.label < t[2]) { _.label = t[2]; _.ops.push(op); break; }
                    if (t[2]) _.ops.pop();
                    _.trys.pop(); continue;
            }
            op = body.call(thisArg, _);
        } catch (e) { op = [6, e]; y = 0; } finally { f = t = 0; }
        if (op[0] & 5) throw op[1]; return { value: op[0] ? op[1] : void 0, done: true };
    }
};
(function () {
    var ApplePayManager = /** @class */ (function () {
        function ApplePayManager(rootElementSelector) {
            this.rootElementSelector = rootElementSelector;
            this.rootElement = null;
            this.applePayVersion = null;
            this.amount = null;
            this.currency = null;
            this.country = null;
            this.label = null;
            this.validateMerchantEndpoint = '/validate-merchant';
            this.authorizePaymentEndpoint = '/authorize-payment';
            this.supportedNetworks = [
                'masterCard',
                'visa',
                'mada'
            ];
            this.merchantCapabilities = [
                'supports3DS',
                'supportsCredit',
                'supportsDebit'
            ];
            this.session = null;
            this.enabled = false;
            this.onCancel = null;
            this.onPaymentSuccess = null;
            this.translations = {
                not_configured: 'Apple Pay is not properly configured',
                not_supported: 'Apple Pay is not supported on your browser'
            };
        }
        ApplePayManager.prototype.initiate = function (configuration) {
            if (!this._rootElement()) {
                return;
            }
            if (!this._isApplePaySupported()) {
                this._showNotSupportedMessage();
                return;
            }
            if (!this._parseConfigurations(configuration)) {
                this._showInvalidConfigurationsMessage();
                return;
            }
            if (configuration.onCancel) {
                this.onCancel = configuration.onCancel;
            }
            if (configuration.onPaymentSuccess) {
                this.onPaymentSuccess = configuration.onPaymentSuccess;
            }
            this._initiateApplePayButton();
        };
        ApplePayManager.prototype._enableButton = function () {
            this.enabled = true;
        };
        ApplePayManager.prototype._disableButton = function () {
            this.enabled = false;
        };
        ApplePayManager.prototype._rootElement = function () {
            if (this.rootElement) {
                return this.rootElement;
            }
            return this.rootElement = document.querySelector(this.rootElementSelector);
        };
        ApplePayManager.prototype._removeRootElement = function () {
            this._rootElement().parentElement.removeChild(this._rootElement());
        };
        ApplePayManager.prototype._removeAllRootElementClasses = function () {
            var _this = this;
            this._rootElement().classList.forEach(function (cls) { return _this._rootElement().classList.remove(cls); });
        };
        ApplePayManager.prototype._parseConfigurations = function (configuration) {
            return this._parseApplePayVersion(configuration) &&
                this._parseAmount(configuration) &&
                this._parseCurrency(configuration) &&
                this._parseCountry(configuration) &&
                this._parseLabel(configuration) &&
                this._parseValidateMerchantEndpoint(configuration) &&
                this._parseAuthorizePaymentEndpoint(configuration) &&
                this._parseTranslations(configuration);
        };
        ApplePayManager.prototype._parseApplePayVersion = function (configuration) {
            try {
                this.applePayVersion = parseInt(configuration.version);
            }
            catch (e) {
                return false;
            }
            return this.applePayVersion > 5;
        };
        ApplePayManager.prototype._parseAmount = function (configuration) {
            return (this.amount = configuration.amount) != undefined;
        };
        ApplePayManager.prototype._parseCurrency = function (configuration) {
            this.currency = configuration.currency;
            return /^[A-Z]{3}$/.test(this.currency);
        };
        ApplePayManager.prototype._parseCountry = function (configuration) {
            this.country = configuration.country;
            return /^[A-Z]{2}$/.test(this.country);
        };
        ApplePayManager.prototype._parseLabel = function (configuration) {
            this.label = configuration.label;
            return typeof this.label === 'string' && this.label.length > 0;
        };
        ApplePayManager.prototype._parseValidateMerchantEndpoint = function (configuration) {
            if (configuration.validateMerchantEndpoint) {
                this.validateMerchantEndpoint = configuration.validateMerchantEndpoint;
            }
            return true;
        };
        ApplePayManager.prototype._parseAuthorizePaymentEndpoint = function (configuration) {
            if (configuration.authorizePaymentEndpoint) {
                this.authorizePaymentEndpoint = configuration.authorizePaymentEndpoint;
            }
            return true;
        };
        ApplePayManager.prototype._parseTranslations = function (configuration) {
            var _a, _b;
            this.translations.not_configured = (_a = configuration.translations.not_configured) !== null && _a !== void 0 ? _a : this.translations.not_configured;
            this.translations.not_supported = (_b = configuration.translations.not_supported) !== null && _b !== void 0 ? _b : this.translations.not_supported;
            return true;
        };
        ApplePayManager.prototype._isApplePaySupported = function () {
            try {
                return window.ApplePaySession && window.ApplePaySession.canMakePayments();
            }
            catch (e) {
                return false;
            }
        };
        ApplePayManager.prototype._showInvalidConfigurationsMessage = function () {
            var message = document.createElement('p');
            message.textContent = this.translations.not_configured;
            this._rootElement().appendChild(message);
            this._removeAllRootElementClasses();
            this._rootElement().classList.add('apple-pay-message');
            this._rootElement().classList.add('apple-pay-message-error');
        };
        ApplePayManager.prototype._showNotSupportedMessage = function () {
            var message = document.createElement('p');
            message.textContent = this.translations.not_supported;
            this._rootElement().appendChild(message);
            this._removeAllRootElementClasses();
            this._rootElement().classList.add('apple-pay-message');
            this._rootElement().classList.add('apple-pay-message-passive-warning');
        };
        ApplePayManager.prototype._initiateApplePayButton = function () {
            var _this = this;
            this._rootElement().classList.remove('apple-pay-button-area');
            this._rootElement().classList.add('apple-pay-button');
            this._rootElement().classList.add('apple-pay-button-black');
            this._rootElement().addEventListener('click', function (event) { return _this._handleApplePayButtonClick(event); });
            this._enableButton();
        };
        ApplePayManager.prototype._handleApplePayButtonClick = function (event) {
            if (!this.enabled) {
                return;
            }
            this._buildSession();
            this._hookupHandlers();
            this.session.begin();
        };
        ApplePayManager.prototype._buildSession = function () {
            return this.session = new ApplePaySession(this.applePayVersion, {
                countryCode: this.country,
                currencyCode: this.currency,
                supportedNetworks: this.supportedNetworks,
                merchantCapabilities: this.merchantCapabilities,
                total: {
                    label: this.label,
                    amount: this.amount
                },
            });
        };
        ApplePayManager.prototype._hookupHandlers = function () {
            var _this = this;
            this.session.onvalidatemerchant = function (e) { return _this._onValidateMerchantHandler(e); };
            this.session.onpaymentauthorized = function (e) { return _this._onPaymentAuthorizedEventHandler(e); };
            this.session.oncancel = function (e) { return _this._onCanceledEventHandler(e); };
        };
        ApplePayManager.prototype._onValidateMerchantHandler = function (event) {
            return __awaiter(this, void 0, void 0, function () {
                var validationUrl, response, e_1, appleResponse;
                return __generator(this, function (_a) {
                    switch (_a.label) {
                        case 0:
                            validationUrl = event.validationURL;
                            response = null;
                            _a.label = 1;
                        case 1:
                            _a.trys.push([1, 3, , 4]);
                            return [4 /*yield*/, this._fetch(this.validateMerchantEndpoint, 'POST', {
                                'validation_url': validationUrl
                            })];
                        case 2:
                            response = _a.sent();
                            return [3 /*break*/, 4];
                        case 3:
                            e_1 = _a.sent();
                            this.session.abort();
                            console.log(e_1);
                            return [2 /*return*/];
                        case 4: return [4 /*yield*/, response.json()];
                        case 5:
                            appleResponse = _a.sent();
                            try {
                                this.session.completeMerchantValidation(appleResponse);
                            }
                            catch (e) {
                                this.session.abort();
                                console.log(e);
                            }
                            return [2 /*return*/];
                    }
                });
            });
        };
        ApplePayManager.prototype._onPaymentAuthorizedEventHandler = function (event) {
            return __awaiter(this, void 0, void 0, function () {
                var response, e_2, result, e_3;
                return __generator(this, function (_a) {
                    switch (_a.label) {
                        case 0:
                            response = null;
                            _a.label = 1;
                        case 1:
                            _a.trys.push([1, 3, , 4]);
                            return [4 /*yield*/, this._fetch(this.authorizePaymentEndpoint, 'POST', {
                                'payment_data': JSON.stringify(event.payment.token.paymentData)
                            })];
                        case 2:
                            response = _a.sent();
                            return [3 /*break*/, 4];
                        case 3:
                            e_2 = _a.sent();
                            this._completeFailedPayment();
                            return [2 /*return*/];
                        case 4:
                            result = null;
                            _a.label = 5;
                        case 5:
                            _a.trys.push([5, 7, , 8]);
                            return [4 /*yield*/, response.json()];
                        case 6:
                            result = _a.sent();
                            return [3 /*break*/, 8];
                        case 7:
                            e_3 = _a.sent();
                            this._completeFailedPayment();
                            return [2 /*return*/];
                        case 8:
                            if (result.success !== true) {
                                this._completeFailedPayment();
                                return [2 /*return*/];
                            }
                            this._completeSuccessfulPayment();
                            if (this.onPaymentSuccess && typeof this.onPaymentSuccess === 'function') {
                                this.onPaymentSuccess(result);
                            }
                            return [2 /*return*/];
                    }
                });
            });
        };
        ApplePayManager.prototype._completeSuccessfulPayment = function () {
            this.session.completePayment({ status: ApplePaySession.STATUS_SUCCESS });
        };
        ApplePayManager.prototype._completeFailedPayment = function () {
            this.session.completePayment({ status: ApplePaySession.STATUS_FAILURE, errors: [] });
        };
        ApplePayManager.prototype._onCanceledEventHandler = function (event) {
            return __awaiter(this, void 0, void 0, function () {
                return __generator(this, function (_a) {
                    if (this.onCancel && typeof this.onCancel === 'function') {
                        this.onCancel(event);
                    }
                    return [2 /*return*/];
                });
            });
        };
        ApplePayManager.prototype._fetch = function (url, method, data) {
            if (method === void 0) { method = 'GET'; }
            if (data === void 0) { data = null; }
            var options = {
                method: method
            };
            if (data != null) {
                options.headers = {
                    'Content-Type': 'application/json'
                };
                options.body = JSON.stringify(data);
            }
            return fetch(url, options);
        };
        return ApplePayManager;
    }());
    window.ApplePayManager = ApplePayManager;
})();
