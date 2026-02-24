/**
 * Sun Times Calculator
 * 
 * 日出日落时间计算工具
 * 用于根据地理位置和日期计算日出日落时间
 * 
 * @package Folio
 */

(function() {
    'use strict';

    /**
     * 日出日落时间计算器
     */
    window.SunTimesCalculator = {
        
        /**
         * 计算日出日落时间
         * @param {number} latitude - 纬度
         * @param {number} longitude - 经度
         * @param {Date} date - 日期对象（可选，默认为今天）
         * @returns {Object} 包含 sunrise 和 sunset 的对象
         */
        calculate: function(latitude, longitude, date) {
            date = date || new Date();
            
            // 计算一年中的第几天（1-365/366）
            var dayOfYear = this.getDayOfYear(date);
            
            // 计算太阳赤纬角（度）
            var declination = this.calculateDeclination(dayOfYear);
            
            // 计算时角（度）
            var hourAngle = this.calculateHourAngle(latitude, declination);
            
            // 计算日出日落时间（小时）
            var sunriseHour = 12 - hourAngle / 15 - this.getEquationOfTime(dayOfYear) / 60 - (longitude / 15);
            var sunsetHour = 12 + hourAngle / 15 - this.getEquationOfTime(dayOfYear) / 60 - (longitude / 15);
            
            // 转换为 Date 对象
            var sunrise = new Date(date);
            sunrise.setHours(Math.floor(sunriseHour), Math.round((sunriseHour % 1) * 60), 0, 0);
            
            var sunset = new Date(date);
            sunset.setHours(Math.floor(sunsetHour), Math.round((sunsetHour % 1) * 60), 0, 0);
            
            return {
                sunrise: sunrise,
                sunset: sunset,
                sunriseHour: sunriseHour,
                sunsetHour: sunsetHour
            };
        },
        
        /**
         * 获取一年中的第几天
         */
        getDayOfYear: function(date) {
            var start = new Date(date.getFullYear(), 0, 0);
            var diff = date - start;
            return Math.floor(diff / (1000 * 60 * 60 * 24));
        },
        
        /**
         * 计算太阳赤纬角（度）
         */
        calculateDeclination: function(dayOfYear) {
            return 23.45 * Math.sin((360 / 365) * (284 + dayOfYear) * Math.PI / 180);
        },
        
        /**
         * 计算时角（度）
         */
        calculateHourAngle: function(latitude, declination) {
            var latRad = latitude * Math.PI / 180;
            var decRad = declination * Math.PI / 180;
            var cosHourAngle = -Math.tan(latRad) * Math.tan(decRad);
            
            // 限制在 -1 到 1 之间
            cosHourAngle = Math.max(-1, Math.min(1, cosHourAngle));
            
            return Math.acos(cosHourAngle) * 180 / Math.PI;
        },
        
        /**
         * 计算时差（分钟）
         */
        getEquationOfTime: function(dayOfYear) {
            var B = (360 / 365) * (dayOfYear - 81);
            return 9.87 * Math.sin(2 * B * Math.PI / 180) - 7.53 * Math.cos(B * Math.PI / 180) - 1.5 * Math.sin(B * Math.PI / 180);
        },
        
        /**
         * 根据当前时间判断是否应该使用暗色主题
         * @param {number} latitude - 纬度
         * @param {number} longitude - 经度
         * @returns {boolean} true 表示应该使用暗色主题
         */
        shouldUseDarkTheme: function(latitude, longitude) {
            var now = new Date();
            var times = this.calculate(latitude, longitude, now);
            
            // 如果当前时间在日落之后或日出之前，使用暗色主题
            return now >= times.sunset || now < times.sunrise;
        },
        
        /**
         * 获取下次切换主题的时间（毫秒）
         * @param {number} latitude - 纬度
         * @param {number} longitude - 经度
         * @returns {number} 距离下次切换的毫秒数
         */
        getNextSwitchTime: function(latitude, longitude) {
            var now = new Date();
            var times = this.calculate(latitude, longitude, now);
            
            var nextSwitch;
            
            if (now < times.sunrise) {
                // 当前是夜晚，下次切换是日出
                nextSwitch = times.sunrise;
            } else if (now < times.sunset) {
                // 当前是白天，下次切换是日落
                nextSwitch = times.sunset;
            } else {
                // 当前是夜晚，下次切换是明天的日出
                var tomorrow = new Date(now);
                tomorrow.setDate(tomorrow.getDate() + 1);
                var tomorrowTimes = this.calculate(latitude, longitude, tomorrow);
                nextSwitch = tomorrowTimes.sunrise;
            }
            
            return nextSwitch.getTime() - now.getTime();
        }
    };

    // 注意：地理位置功能已移除，现在使用后台设置的日出日落时间

})();

