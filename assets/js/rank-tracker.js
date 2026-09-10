(function () {
  'use strict';

  var config = window.SeoisticRankTracker || {rows: []};
  var rowsById = {};
  (config.rows || []).forEach(function (row) {
    if (row.keyword && row.keyword.id) {
      rowsById[String(row.keyword.id)] = row;
    }
  });

  function svg(name, attrs) {
    var node = document.createElementNS('http://www.w3.org/2000/svg', name);
    Object.keys(attrs || {}).forEach(function (key) {
      node.setAttribute(key, String(attrs[key]));
    });
    return node;
  }

  function points(values, width, height, padding, invert) {
    var min = Math.min.apply(null, values.concat([1]));
    var max = Math.max.apply(null, values.concat([100]));
    var span = max - min || 1;
    return values.map(function (value, index) {
      var x = padding + (index / Math.max(1, values.length - 1)) * (width - padding * 2);
      var ratio = (value - min) / span;
      var y = padding + (invert ? 1 - ratio : ratio) * (height - padding * 2);
      return [x, y];
    });
  }

  function polyline(coords) {
    return coords.map(function (coord) { return coord[0].toFixed(1) + ',' + coord[1].toFixed(1); }).join(' ');
  }

  function drawSparkline(svgNode, history) {
    var values = history.map(function (item) { return Number(item.position); }).filter(Number.isFinite);
    if (!values.length) return;
    var coords = points(values, 96, 24, 2, false);
    svgNode.appendChild(svg('polyline', {
      points: polyline(coords),
      fill: 'none',
      stroke: 'currentColor',
      'stroke-width': '2',
      'stroke-linecap': 'round',
      'stroke-linejoin': 'round'
    }));
    var last = coords[coords.length - 1];
    svgNode.appendChild(svg('circle', {cx: last[0], cy: last[1], r: 2, fill: 'currentColor'}));
  }

  function drawChart(svgNode, history, i18n) {
    svgNode.textContent = '';
    if (!history.length) {
      var label = svg('text', {x: 320, y: 92, 'text-anchor': 'middle', class: 'seoistic-rank-empty'});
      label.textContent = i18n.noHistory;
      svgNode.appendChild(label);
      return;
    }
    var values = history.map(function (item) { return Number(item.position); });
    var coords = points(values, 640, 180, 20, false);
    [1, 10, 20, 50, 100].forEach(function (rank) {
      var y = points([rank], 640, 180, 20, false)[0][1];
      svgNode.appendChild(svg('line', {x1: 20, y1: y, x2: 620, y2: y, class: rank === 10 ? 'is-primary' : ''}));
      var rankLabel = svg('text', {x: 8, y: y + 4, 'text-anchor': 'end'});
      rankLabel.textContent = String(rank);
      svgNode.appendChild(rankLabel);
    });
    var area = polyline(coords) + ' 620,160 20,160';
    svgNode.appendChild(svg('polygon', {points: area, class: 'seoistic-rank-area'}));
    svgNode.appendChild(svg('polyline', {
      points: polyline(coords),
      class: 'seoistic-rank-line',
      fill: 'none',
      'stroke-width': '3',
      'stroke-linecap': 'round',
      'stroke-linejoin': 'round'
    }));
    coords.forEach(function (coord, index) {
      if (index !== coords.length - 1 && index !== 0 && index !== Math.floor((coords.length - 1) / 2)) return;
      var point = svg('circle', {cx: coord[0], cy: coord[1], r: 4, class: 'seoistic-rank-point'});
      svgNode.appendChild(point);
    });
    history.forEach(function (item, index) {
      if (!item.checked_at) return;
      var coord = coords[index];
      var dateLabel = svg('text', {x: coord[0], y: 174, 'text-anchor': index === 0 ? 'start' : (index === history.length - 1 ? 'end' : 'middle')});
      dateLabel.textContent = String(item.checked_at).slice(0, 10);
      svgNode.appendChild(dateLabel);
    });
  }

  document.querySelectorAll('[data-rank-sparkline]').forEach(function (node) {
    var row = rowsById[node.getAttribute('data-rank-sparkline')];
    if (row) drawSparkline(node, row.history || []);
  });

  document.querySelectorAll('[data-rank-history-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
      var id = button.getAttribute('data-rank-history-toggle');
      var row = document.querySelector('[data-rank-history="' + id + '"]');
      if (!row) return;
      var open = !row.hidden;
      row.hidden = open;
      button.setAttribute('aria-expanded', String(!open));
      button.textContent = open ? (config.i18n && config.i18n.showHistory) : (config.i18n && config.i18n.hideHistory);
      if (!open) {
        var chart = row.querySelector('[data-rank-chart]');
        var data = rowsById[id];
        if (chart && data) drawChart(chart, data.history || [], config.i18n || {});
      }
    });
  });

  function printReport() {
    var frame = document.querySelector('[data-rank-report-frame]');
    if (!frame) return;
    try { frame.contentWindow.focus(); frame.contentWindow.print(); } catch (error) {
      window.alert(config.i18n && config.i18n.printUnavailable ? config.i18n.printUnavailable : 'Open the report in a new tab to print it.');
    }
  }

  document.addEventListener('click', function (event) {
    var target = event.target.closest('[data-rank-report-print]');
    if (target) printReport();
  });
}());
