angular.module('openITCOCKPIT').directive('serviceAvailabilityBarChart', function($http, $timeout, AvailabilityColorCalculationService){
    return {
        restrict: 'E',
        templateUrl: '/instantreports/serviceAvailabilityBarChart.html',
        scope: {
            'data': '=',
            'chartId': '='
        },
        controller: function($scope){
            $timeout(function(){
                var paper = Raphael('serviceBarChart-' + $scope.chartId)
                    .hbarchart(3, 4, 350, 26, [
                        [$scope.data.reportData[0]],
                        [$scope.data.reportData[1]],
                        [$scope.data.reportData[2]],
                        [$scope.data.reportData[3]]
                    ], {
                        colors: ["#00C851", "#ffbb33", "#CC0000", "#727b84"],
                        values: [
                            55, 10, 20, 10
                        ],
                        labels: [
                            $scope.data.reportData.percentage[0],
                            $scope.data.reportData.percentage[1],
                            $scope.data.reportData.percentage[2],
                            $scope.data.reportData.percentage[3]
                        ],
                        stacked: true,
                        legend: true,
                        show3d: true,
                        legendShowValues: false,
                        legendContainerCssClass: "barchart-legend-container col-lg-6 col-md-12",
                        legendLabelCssClass: "barchart-legend-label",
                        legendMarker: "square",
                        size3d: 6
                    });
            });
        },

        link: function(){
        }
    };
});
