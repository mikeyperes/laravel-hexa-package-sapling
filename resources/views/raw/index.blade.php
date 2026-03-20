@extends('layouts.app')

@section('title', 'Sapling Raw — ' . config('hws.app_name'))
@section('header', 'Sapling — Raw Functions')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    {{-- API Key Status --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <span class="inline-block w-3 h-3 rounded-full {{ $hasApiKey ? 'bg-green-500' : 'bg-yellow-500' }}"></span>
            <span class="text-sm font-medium text-gray-700">API Key:</span>
            <span class="text-sm text-gray-500 font-mono">{{ $hasApiKey ? $maskedKey : 'Not configured' }}</span>
        </div>
        <span class="text-xs font-medium px-2 py-1 rounded-full {{ $hasApiKey ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
            {{ $hasApiKey ? 'Active' : 'Missing' }}
        </span>
    </div>

    {{-- Package Functions Index --}}
    <div class="bg-gray-900 rounded-xl p-6 text-sm font-mono">
        <h2 class="text-white font-semibold mb-3">Sapling Functions</h2>
        <table class="w-full text-left">
            <thead>
                <tr class="text-gray-400 border-b border-gray-700">
                    <th class="py-1.5 px-2">Function</th>
                    <th class="py-1.5 px-2">Method</th>
                    <th class="py-1.5 px-2">Route</th>
                    <th class="py-1.5 px-2">Status</th>
                </tr>
            </thead>
            <tbody class="text-gray-300">
                <tr class="border-b border-gray-800">
                    <td class="py-1.5 px-2">Test API key validity</td>
                    <td class="py-1.5 px-2 text-blue-400">testApiKey()</td>
                    <td class="py-1.5 px-2 text-green-400">POST /settings/sapling/test</td>
                    <td class="py-1.5 px-2 text-green-400">LIVE</td>
                </tr>
                <tr class="border-b border-gray-800">
                    <td class="py-1.5 px-2">Detect AI-generated content</td>
                    <td class="py-1.5 px-2 text-blue-400">detect(text)</td>
                    <td class="py-1.5 px-2 text-green-400">POST /sapling/detect</td>
                    <td class="py-1.5 px-2 text-green-400">LIVE</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Detect AI Content --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Detect AI Content</h2>

        <div class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Text to Analyze</label>
                <textarea id="sapling-text" rows="6" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Paste text here to check for AI-generated content (minimum 50 characters)..."></textarea>
            </div>
            <button id="btn-sapling-detect" class="px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition-colors">
                Detect AI
            </button>
        </div>

        <div id="sapling-detect-result" class="mt-4"></div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('btn-sapling-detect').addEventListener('click', function() {
    var btn = this;
    var originalText = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin h-4 w-4 inline mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Detecting...';

    var resultDiv = document.getElementById('sapling-detect-result');
    var text = document.getElementById('sapling-text').value;

    fetch('{{ route("sapling.detect") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ text: text })
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        var html = '';
        if (data.success && data.data) {
            var score = data.data.score;
            var colorClass = score > 70 ? 'text-red-600' : (score > 40 ? 'text-yellow-600' : 'text-green-600');
            var bgClass = score > 70 ? 'bg-red-50 border-red-200' : (score > 40 ? 'bg-yellow-50 border-yellow-200' : 'bg-green-50 border-green-200');

            html += '<div class="p-4 rounded-lg ' + bgClass + ' border mb-4">';
            html += '<div class="flex items-center justify-between mb-2">';
            html += '<span class="text-sm font-medium text-gray-700">AI Detection Score</span>';
            html += '<span class="text-2xl font-bold ' + colorClass + '">' + score.toFixed(1) + '%</span>';
            html += '</div>';
            html += '<div class="w-full bg-gray-200 rounded-full h-3">';
            html += '<div class="h-3 rounded-full transition-all ' + (score > 70 ? 'bg-red-500' : (score > 40 ? 'bg-yellow-500' : 'bg-green-500')) + '" style="width: ' + Math.min(score, 100) + '%"></div>';
            html += '</div>';
            html += '<p class="text-xs text-gray-500 mt-2">' + data.message + '</p>';
            html += '</div>';

            // Sentence breakdown
            if (data.data.sentence_scores && data.data.sentence_scores.length > 0) {
                html += '<div class="space-y-2">';
                html += '<h3 class="text-sm font-semibold text-gray-700">Sentence Breakdown</h3>';
                data.data.sentence_scores.forEach(function(item) {
                    var sentScore = (item.score !== undefined ? item.score : item[1]) * 100;
                    var sentText = item.sentence !== undefined ? item.sentence : item[0];
                    var sentColor = sentScore > 70 ? 'border-red-300 bg-red-50' : (sentScore > 40 ? 'border-yellow-300 bg-yellow-50' : 'border-green-300 bg-green-50');
                    html += '<div class="p-3 rounded-lg border ' + sentColor + '">';
                    html += '<div class="flex items-start justify-between gap-3">';
                    html += '<p class="text-sm text-gray-800 break-words flex-1">' + escapeHtml(sentText) + '</p>';
                    html += '<span class="text-xs font-mono font-bold whitespace-nowrap">' + sentScore.toFixed(1) + '%</span>';
                    html += '</div></div>';
                });
                html += '</div>';
            }
        } else {
            html = '<div class="p-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-800">' + escapeHtml(data.message || 'Error') + '</div>';
        }
        resultDiv.innerHTML = html;
    })
    .catch(function(err) {
        resultDiv.innerHTML = '<div class="p-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-800">Request failed: ' + escapeHtml(err.message) + '</div>';
    })
    .finally(function() {
        btn.disabled = false;
        btn.textContent = originalText;
    });
});

function escapeHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str || ''));
    return div.innerHTML;
}
</script>
@endpush
@endsection
