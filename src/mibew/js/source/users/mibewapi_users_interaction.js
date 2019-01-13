/*!
 * This file is a part of Mibew Messenger.
 *
 * Copyright 2005-2019 the original author or authors.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Represents User list Window to core interaction type
 *
 * @constructor
 */
MibewAPIUsersInteraction = function() {
    this.mandatoryArguments = function() {
        return {
            '*': {
                'agentId': null,
                'return': {},
                'references': {}
            },
            'result': {
                'errorCode': 0
            }
        };
    };

    this.getReservedFunctionsNames = function() {
        return [
            'result'
        ];
    };
};
MibewAPIUsersInteraction.prototype = new MibewAPIInteraction();