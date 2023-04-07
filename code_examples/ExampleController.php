<?php

declare(strict_types=1);

namespace App\Api\Frontend\Exchange\Controllers;

use App\Api\Requests\CalculateRequest;
use App\Api\Resources\CalculateResource;
use Core\Drivers\Processing\Contracts\HasUserErrorContract;
use Core\Drivers\Processing\Exceptions\AbstractProcessingException;
use Core\Exceptions\AbstractValidationException;
use Core\Resources\Error400Resource;
use Core\Resources\Error422Resource;
use Domain\Balances\Exceptions\InsufficientFundsException;
use Domain\RiskLevel\Enums\VerificationLevelEnum;
use Domain\Languages\Services\TranslationService;
use Domain\Transactions\Actions\CalculateAction;
use Domain\Transactions\Events\CalculatedEvent;
use Domain\Transactions\Exceptions\Exchange\ExchangeMaxAmountException;
use Domain\Transactions\Exceptions\Exchange\ExchangeMinAmountException;
use Domain\Transactions\Exceptions\UnexpectedBehaviourException;
use Domain\Users\Models\UserModel;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Psr\Log\LoggerInterface;
use Throwable;

class ExampleController extends Controller
{
    public function calculate(CalculateRequest $request, LoggerInterface $logger): JsonResource
    {
        /** @var UserModel $user */
        $user = auth()->user();
        $currencyFromId = (int)$request->input('currency_id_from');
        $currencyToId = (int)$request->input('currency_id_to');
        $currencyIdAmount = (int)$request->input('currency_id_amount');
        $amount = (string)$request->input('amount');

        if ($user->verification_level->equals(VerificationLevelEnum::zero())) {
            return Error400Resource::make([
                'error' => TranslationService::translate('kyc.unavailable_for_zero_level'),
            ]);
        }

        try {
            $calculateResult = (new CalculateAction())->execute(new CalculateActionData([
                'userId' => $user->id,
                'userAmount' => $amount,
                'userAmountCurrencyId' => $currencyIdAmount,
                'currencyFromId' => $currencyFromId,
                'currencyToId' => $currencyToId,
            ]));
        } catch (AbstractValidationException $exception) {
            return Error400Resource::make([$exception->getFieldName() => $exception->getTranslateMessage()]);
        } catch (AbstractProcessingException $exception) {
            if ($exception instanceof HasUserErrorContract) {
                return Error400Resource::make(['error' => $exception->getTranslateMessage()]);
            }

            return Error422Resource::make([
                'error' => TranslationService::translate('transaction.errors.error_from_processing'),
            ]);
        } catch (InsufficientFundsException | ExchangeMinAmountException | ExchangeMaxAmountException $exception) {
            return Error400Resource::make(['amount' => $exception->getTranslateMessage()]);
        } catch (UnexpectedBehaviourException | Throwable $exception) {
            $logger->error($exception->getMessage());
            report($exception);

            return Error422Resource::make([
                'error' => TranslationService::translate('transaction.errors.unexpected_behavior'),
            ]);
        }


        $exchangeCalculatedEvent = new CalculatedEvent($calculateResult);

        event($exchangeCalculatedEvent);

        return new CalculateResource($exchangeCalculatedEvent);
    }
}
