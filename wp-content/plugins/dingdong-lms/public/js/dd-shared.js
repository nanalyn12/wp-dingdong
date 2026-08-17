/**
 * DingDong LMS — 공용 상수/헬퍼
 * 다른 모듈 IIFE보다 먼저 로드되어야 함.
 */
(function () {
    'use strict';

    /**
     * Gemini 모델 fallback 체인
     * 안정 모델(2.x) 우선, preview(3.x)는 후순위.
     * 새 모델이 나오면 여기만 업데이트.
     */
    window.DDGeminiModels = [
        'gemini-2.5-flash',
        'gemini-2.0-flash',
        'gemini-2.5-flash-lite',
        'gemini-3.1-flash-lite',
        'gemini-3.5-flash',
        'gemini-3-flash-preview'
    ];

    /** Gemini API 엔드포인트 prefix */
    window.DDGeminiApiBase = 'https://generativelanguage.googleapis.com/v1beta/models/';
})();
