<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Sales\Test\Unit\Model\Order\Email\Sender;

use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;

class InvoiceSenderTest extends AbstractSenderTest
{
    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $sender;

    /**
     * @var \Magento\Sales\Model\Order\Invoice|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $invoiceMock;

    /**
     * @var \Magento\Sales\Model\Resource\EntityAbstract|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $invoiceResourceMock;

    protected function setUp()
    {
        $this->stepMockSetup();

        $this->invoiceResourceMock = $this->getMock(
            '\Magento\Sales\Model\Resource\Order\Invoice',
            ['saveAttribute'],
            [],
            '',
            false
        );

        $this->invoiceMock = $this->getMock(
            '\Magento\Sales\Model\Order\Invoice',
            [
                'getStore', '__wakeup', 'getOrder',
                'setSendEmail', 'setEmailSent', 'getCustomerNoteNotify',
                'getCustomerNote'
            ],
            [],
            '',
            false
        );
        $this->invoiceMock->expects($this->any())
            ->method('getStore')
            ->will($this->returnValue($this->storeMock));
        $this->invoiceMock->expects($this->any())
            ->method('getOrder')
            ->will($this->returnValue($this->orderMock));

        $this->identityContainerMock = $this->getMock(
            '\Magento\Sales\Model\Order\Email\Container\InvoiceIdentity',
            ['getStore', 'isEnabled', 'getConfigValue', 'getTemplateId', 'getGuestTemplateId'],
            [],
            '',
            false
        );
        $this->identityContainerMock->expects($this->any())
            ->method('getStore')
            ->will($this->returnValue($this->storeMock));

        $this->sender = new InvoiceSender(
            $this->templateContainerMock,
            $this->identityContainerMock,
            $this->senderBuilderFactoryMock,
            $this->loggerMock,
            $this->paymentHelper,
            $this->invoiceResourceMock,
            $this->globalConfig,
            $this->addressRenderer,
            $this->eventManagerMock
        );
    }

    /**
     * @param int $configValue
     * @param bool|null $forceSyncMode
     * @param bool|null $customerNoteNotify
     * @param bool|null $emailSendingResult
     * @dataProvider sendDataProvider
     * @return void
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testSend($configValue, $forceSyncMode, $customerNoteNotify, $emailSendingResult)
    {
        $comment = 'comment_test';
        $address = 'address_test';
        $configPath = 'sales_email/general/async_sending';

        $this->invoiceMock->expects($this->once())
            ->method('setSendEmail')
            ->with(true);

        $this->globalConfig->expects($this->once())
            ->method('getValue')
            ->with($configPath)
            ->willReturn($configValue);

        if (!$configValue || $forceSyncMode) {
            $addressMock = $this->getMock(
                'Magento\Sales\Model\Order\Address',
                [],
                [],
                '',
                false
            );

            $this->addressRenderer->expects($this->any())
                ->method('format')
                ->with($addressMock, 'html')
                ->willReturn($address);

            $this->orderMock->expects($this->any())
                ->method('getBillingAddress')
                ->willReturn($addressMock);

            $this->orderMock->expects($this->any())
                ->method('getShippingAddress')
                ->willReturn($addressMock);

            $this->invoiceMock->expects($this->once())
                ->method('getCustomerNoteNotify')
                ->willReturn($customerNoteNotify);

            $this->invoiceMock->expects($this->any())
                ->method('getCustomerNote')
                ->willReturn($comment);

            $this->templateContainerMock->expects($this->once())
                ->method('setTemplateVars')
                ->with(
                    [
                        'order' => $this->orderMock,
                        'invoice' => $this->invoiceMock,
                        'comment' => $customerNoteNotify ? $comment : '',
                        'billing' => $addressMock,
                        'payment_html' => 'payment',
                        'store' => $this->storeMock,
                        'formattedShippingAddress' => $address,
                        'formattedBillingAddress' => $address
                    ]
                );

            $this->identityContainerMock->expects($this->once())
                ->method('isEnabled')
                ->willReturn($emailSendingResult);

            if ($emailSendingResult) {
                $this->senderBuilderFactoryMock->expects($this->once())
                    ->method('create')
                    ->willReturn($this->senderMock);

                $this->senderMock->expects($this->once())->method('send');

                $this->senderMock->expects($this->once())->method('sendCopyTo');

                $this->invoiceMock->expects($this->once())
                    ->method('setEmailSent')
                    ->with(true);

                $this->invoiceResourceMock->expects($this->once())
                    ->method('saveAttribute')
                    ->with($this->invoiceMock, ['send_email', 'email_sent']);

                $this->assertTrue(
                    $this->sender->send($this->invoiceMock)
                );
            } else {
                $this->invoiceResourceMock->expects($this->once())
                    ->method('saveAttribute')
                    ->with($this->invoiceMock, 'send_email');

                $this->assertFalse(
                    $this->sender->send($this->invoiceMock)
                );
            }
        } else {
            $this->invoiceResourceMock->expects($this->once())
                ->method('saveAttribute')
                ->with($this->invoiceMock, 'send_email');

            $this->assertFalse(
                $this->sender->send($this->invoiceMock)
            );
        }
    }

    /**
     * @return array
     */
    public function sendDataProvider()
    {
        return [
            [0, 0, 1, true],
            [0, 0, 0, true],
            [0, 0, 1, false],
            [0, 0, 0, false],
            [0, 1, 1, true],
            [0, 1, 0, true],
            [1, null, null, null]
        ];
    }
}
